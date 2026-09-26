<?php
/**
 * Vérification publique d'un reçu (cible du QR code imprimé sur chaque reçu).
 *
 * URL : verifier-recu.php?n=REC-2026-000123&h=SIGNATURE
 * La signature HMAC du numéro est recalculée avec la clé secrète : un numéro
 * inventé ou modifié produit « Reçu invalide ». Aucune connexion n'est requise.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/core/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'");
header('Cache-Control: no-store');

$numero = is_string($_GET['n'] ?? null) ? trim($_GET['n']) : '';
$signature = is_string($_GET['h'] ?? null) ? trim($_GET['h']) : '';

$paiement = null;
try {
    if ($numero !== '' && $signature !== '' && GenerateurRecu::verifierSignature($numero, $signature)) {
        $recu = Recu::trouverParNumero($numero);
        $paiement = $recu ? Paiement::trouver((int) $recu['id_paiement']) : null;
        // Seul un paiement réussi peut avoir un reçu authentique.
        if ($paiement !== null && $paiement['statut'] !== 'reussi') {
            $paiement = null;
        }
    }
} catch (Throwable $e) {
    gererExceptionFatale($e);
    exit;
}

if ($paiement === null) {
    http_response_code(404);
    journaliser('app', 'Vérification de reçu refusée : n=' . mb_substr($numero, 0, 40) . ' depuis ' . adresseIpClient());
}

echo View::rendre('recus/verification', [
    'titre'    => $paiement ? 'Reçu authentique' : 'Reçu invalide',
    'paiement' => $paiement,
    'numero'   => $numero,
], 'layouts/simple');
