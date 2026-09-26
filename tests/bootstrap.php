<?php
/**
 * Amorçage des tests PHPUnit : configuration de l'application + base de test dédiée.
 * Les variables DB_NAME, RECUS_DIR et LOGS_DIR sont fixées par phpunit.xml.
 */
declare(strict_types=1);

// Chemins relatifs de phpunit.xml → absolus (le répertoire courant peut varier).
foreach (['RECUS_DIR', 'LOGS_DIR'] as $variable) {
    $valeur = getenv($variable);
    if ($valeur !== false && !preg_match('#^([a-z]:)?[\\\\/]#i', $valeur)) {
        putenv($variable . '=' . dirname(__DIR__) . '/' . $valeur);
    }
}

require dirname(__DIR__) . '/core/bootstrap.php';
require __DIR__ . '/BaseDeTest.php';

if (DB_NAME === 'gestion_frais') {
    exit("Sécurité : les tests refusent de s'exécuter sur la base de démonstration.\n");
}
