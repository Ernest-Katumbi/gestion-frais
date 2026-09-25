<?php
/**
 * Notification de la passerelle de paiement (appel serveur à serveur).
 *
 * Corps JSON : {"reference": "PAY-…", "status": "SUCCESS" | "FAILED", "reason": "…"}
 * En-tête    : X-Signature = HMAC-SHA256(corps brut, secret partagé)
 *
 * Réponses : 200 traité (ou déjà traité), 400 notification illisible,
 *            401 signature invalide, 404 référence inconnue, 405 méthode, 500 erreur.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function repondre(int $code, string $message): never
{
    http_response_code($code);
    echo json_encode(['message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    repondre(405, 'Méthode non autorisée.');
}

// 1. Lecture du corps brut et vérification de la signature.
$corps = (string) file_get_contents('php://input');
$signature = (string) ($_SERVER['HTTP_X_SIGNATURE'] ?? '');

if (!passerelle()->verifierSignature($corps, $signature)) {
    journaliser('app', 'Callback refusé : signature invalide depuis ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    repondre(401, 'Signature invalide.');
}

$notification = json_decode($corps, true);
$reference = is_array($notification) ? (string) ($notification['reference'] ?? '') : '';
$statut = is_array($notification) ? (string) ($notification['status'] ?? '') : '';
$raison = is_array($notification) ? mb_substr(trim((string) ($notification['reason'] ?? '')), 0, 120) : '';

if (!preg_match(Paiement::FORMAT_REFERENCE, $reference) || !in_array($statut, ['SUCCESS', 'FAILED'], true)) {
    repondre(400, 'Notification illisible.');
}

// 2. Traitement dans une seule transaction.
$db = Database::get();
$db->beginTransaction();
try {
    // Verrou sur le paiement : deux notifications simultanées sont traitées l'une après l'autre.
    $requete = $db->prepare(
        'SELECT id_paiement, id_frais, montant, statut FROM paiement WHERE reference = ? FOR UPDATE'
    );
    $requete->execute([$reference]);
    $paiement = $requete->fetch();

    if ($paiement === false) {
        $db->rollBack();
        repondre(404, 'Paiement inconnu.');
    }

    // Idempotence : un paiement déjà traité n'est jamais modifié une seconde fois.
    if ($paiement['statut'] !== 'en_attente') {
        $db->rollBack();
        journaliser('app', "Callback ignoré : $reference déjà traité ({$paiement['statut']}).");
        repondre(200, 'Notification déjà traitée.');
    }

    $idPaiement = (int) $paiement['id_paiement'];

    if ($statut === 'SUCCESS') {
        $db->prepare("UPDATE paiement SET statut = 'reussi' WHERE id_paiement = ?")->execute([$idPaiement]);
        Frais::enregistrerVersement((int) $paiement['id_frais'], (float) $paiement['montant']);
        GenerateurRecu::generer($idPaiement);
        Notificateur::paiementConfirme($idPaiement);
    } else {
        $db->prepare("UPDATE paiement SET statut = 'echoue' WHERE id_paiement = ?")->execute([$idPaiement]);
        Notificateur::paiementEchoue($idPaiement, $raison);
    }

    $db->commit();
    journaliser('app', "Callback traité : $reference → $statut" . ($raison !== '' ? " ($raison)" : ''));
    repondre(200, 'Notification traitée.');
} catch (DomainException $e) {
    // Versement devenu impossible (frais soldé entre-temps) : le paiement est marqué échoué.
    $db->rollBack();
    $db->beginTransaction();
    $db->prepare("UPDATE paiement SET statut = 'echoue' WHERE reference = ? AND statut = 'en_attente'")->execute([$reference]);
    $idPaiement = (int) $paiement['id_paiement'];
    Notificateur::paiementEchoue($idPaiement, 'Montant supérieur au reste à payer : le paiement sera remboursé.');
    $db->commit();
    journaliser('app', "Callback : $reference refusé à l'enregistrement (" . $e->getMessage() . ')');
    repondre(200, 'Notification traitée : versement refusé.');
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    journaliser('app', "Callback en erreur pour $reference : " . $e->getMessage());
    repondre(500, 'Erreur interne.');
}
