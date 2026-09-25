<?php
/**
 * Envoie à public/callback.php une notification de passerelle, signée ou falsifiée
 * (cas de test CT11 et CT12 du cahier de tests).
 *
 *   php tests/envoyer_callback.php                          liste les paiements en attente
 *   php tests/envoyer_callback.php PAY-20260925-ABC123      notification SUCCESS correctement signée
 *   php tests/envoyer_callback.php PAY-… FAILED --raison="Solde insuffisant"
 *   php tests/envoyer_callback.php PAY-… --falsifie         CT11 : signature invalide → 401, rien ne change
 *   php tests/envoyer_callback.php PAY-… --fois=2           CT12 : même notification envoyée deux fois
 */
declare(strict_types=1);

require dirname(__DIR__) . '/core/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit('Script réservé à la ligne de commande.');
}

// Arguments positionnels et options --nom[=valeur], dans n'importe quel ordre.
$arguments = [];
$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $argument, $m)) {
        $options[$m[1]] = $m[2] ?? true;
    } else {
        $arguments[] = $argument;
    }
}
$reference = $arguments[0] ?? '';
$statut = strtoupper($arguments[1] ?? 'SUCCESS');

if ($reference === '') {
    $requete = Database::get()->prepare(
        "SELECT reference, montant, mode, date_paiement FROM paiement WHERE statut = 'en_attente' ORDER BY id_paiement DESC LIMIT 10"
    );
    $requete->execute();
    $enAttente = $requete->fetchAll();
    echo "Usage : php tests/envoyer_callback.php REFERENCE [SUCCESS|FAILED] [--falsifie] [--fois=N] [--raison=\"…\"]\n\n";
    echo $enAttente === [] ? "Aucun paiement en attente. Lancez un paiement en ligne depuis l'espace parent.\n" : "Paiements en attente :\n";
    foreach ($enAttente as $p) {
        echo "  {$p['reference']}  " . formaterMontant($p['montant']) . "  {$p['mode']}  {$p['date_paiement']}\n";
    }
    exit(0);
}
if (!in_array($statut, ['SUCCESS', 'FAILED'], true)) {
    fwrite(STDERR, "Statut attendu : SUCCESS ou FAILED.\n");
    exit(1);
}

$corps = json_encode([
    'reference' => $reference,
    'status'    => $statut,
    'reason'    => (string) ($options['raison'] ?? ($statut === 'FAILED' ? 'Solde insuffisant' : '')),
], JSON_UNESCAPED_UNICODE);

// Signature correcte (secret partagé) ou falsifiée (autre secret).
$signature = isset($options['falsifie'])
    ? hash_hmac('sha256', $corps, 'secret-inconnu-de-l-attaquant')
    : SimulateurPasserelle::signer($corps);
$fois = max(1, (int) ($options['fois'] ?? 1));

$etat = static function () use ($reference): string {
    $p = Paiement::trouverParReference($reference);
    return $p === null
        ? 'paiement introuvable'
        : sprintf('paiement %s · frais payé %s / %s (%s) · reçu %s',
            $p['statut'], $p['frais_paye'], $p['frais_montant'], $p['frais_statut'], $p['recu_numero'] ?? 'aucun');
};

echo 'Callback  : ' . SimulateurPasserelle::urlCallback() . "\n";
echo "Corps     : $corps\n";
echo 'Signature : ' . (isset($options['falsifie']) ? 'FALSIFIÉE' : 'valide') . " ($signature)\n";
echo 'Avant     : ' . $etat() . "\n\n";

for ($i = 1; $i <= $fois; $i++) {
    $reponse = SimulateurPasserelle::envoyer($corps, $signature);
    echo "Envoi $i/$fois → HTTP {$reponse['code']} {$reponse['corps']}\n";
}
echo "\nAprès     : " . $etat() . "\n";
