<?php
/**
 * Rappels d'échéance — à exécuter une fois par jour en ligne de commande :
 *
 *   php cron/rappels_echeances.php                 (échéances d'aujourd'hui à J+3)
 *   php cron/rappels_echeances.php --jours=7       (fenêtre plus large)
 *   php cron/rappels_echeances.php --date=2026-09-26 (simuler un autre jour, pour les tests)
 *
 * Pour chaque frais non soldé dont l'échéance tombe dans la fenêtre, le parent reçoit
 * une notification « echeance » et un e-mail (storage/logs/mail.log). Un même rappel
 * n'est jamais envoyé deux fois le même jour : le script peut être relancé sans risque.
 *
 * Planification sous Windows (Planificateur de tâches), chaque jour à 7 h :
 *   schtasks /create /tn "Rappels frais scolaires" /sc daily /st 07:00
 *            /tr "C:\xampp\php\php.exe C:\xampp\htdocs\gestion-frais\cron\rappels_echeances.php"
 */
declare(strict_types=1);

require dirname(__DIR__) . '/core/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Script réservé à la ligne de commande.');
}

$options = getopt('', ['date::', 'jours::']);
$jour = isset($options['date']) && dateValide((string) $options['date']) ? (string) $options['date'] : date('Y-m-d');
$jours = isset($options['jours']) ? max(0, (int) $options['jours']) : 3;
$aujourdhui = date('Y-m-d'); // jour réel d'exécution, pour l'anti-doublon

$frais = Frais::aRappeler($jour, $jours);
$envoyes = 0;
$ignores = 0;

foreach ($frais as $f) {
    $eleve = $f['eleve_prenom'] . ' ' . $f['eleve_nom'];
    $delai = (int) (new DateTimeImmutable($jour))->diff(new DateTimeImmutable($f['echeance']))->format('%r%a');
    // Début de message stable : sert à détecter un rappel déjà envoyé aujourd'hui.
    $debut = sprintf('Rappel : « %s » de %s arrive à échéance le %s', $f['categorie'], $eleve, formaterDate($f['echeance']));
    $message = $debut
        . ($delai === 0 ? ' (aujourd\'hui)' : ' (dans ' . $delai . ' jour' . ($delai > 1 ? 's' : '') . ')')
        . '. Reste à payer : ' . formaterMontant($f['reste']) . '.';

    if (Notification::rappelDejaEnvoye((int) $f['id_parent'], $debut, $aujourdhui)) {
        $ignores++;
        continue;
    }
    Notificateur::rappelEcheance($f, $message);
    $envoyes++;
    echo "  → {$f['parent_email']} : {$f['categorie']} de $eleve (" . formaterDate($f['echeance']) . ', reste ' . formaterMontant($f['reste']) . ")\n";
}

$resume = sprintf(
    'Rappels d\'échéance du %s (fenêtre de %d jours) : %d frais concerné%s, %d rappel%s envoyé%s, %d déjà envoyé%s aujourd\'hui.',
    formaterDate($jour), $jours,
    count($frais), count($frais) > 1 ? 's' : '',
    $envoyes, $envoyes > 1 ? 's' : '', $envoyes > 1 ? 's' : '',
    $ignores, $ignores > 1 ? 's' : ''
);
echo $resume . PHP_EOL;
journaliser('app', 'Cron — ' . $resume);
