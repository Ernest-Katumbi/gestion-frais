<?php
/**
 * Amorçage commun à toutes les entrées de l'application
 * (public/index.php, public/callback.php, scripts CLI du dossier cron/ et tests).
 */
declare(strict_types=1);

define('RACINE', dirname(__DIR__));

$fichierConfig = RACINE . '/config/config.php';
if (!is_file($fichierConfig)) {
    http_response_code(500);
    exit('Configuration absente : copiez config/config.example.php en config/config.php.');
}
require $fichierConfig;
require RACINE . '/vendor/autoload.php';

// Chargement automatique des classes du projet (sans espace de noms) :
// core/, controllers/, models/ et services/.
spl_autoload_register(static function (string $classe): void {
    foreach (['core', 'controllers', 'models', 'services'] as $dossier) {
        $fichier = RACINE . '/' . $dossier . '/' . $classe . '.php';
        if (is_file($fichier)) {
            require $fichier;
            return;
        }
    }
});

require RACINE . '/core/helpers.php';

date_default_timezone_set(APP_TIMEZONE);
mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');

// Toute alerte PHP devient une exception : aucune erreur n'est ignorée silencieusement.
// Les avis d'obsolescence (souvent issus des bibliothèques) sont seulement journalisés.
set_error_handler(static function (int $niveau, string $message, string $fichier, int $ligne): bool {
    if (!(error_reporting() & $niveau)) {
        return false;
    }
    if ($niveau === E_DEPRECATED || $niveau === E_USER_DEPRECATED) {
        journaliser('app', "Obsolescence : $message ($fichier:$ligne)");
        return true;
    }
    throw new ErrorException($message, 0, $niveau, $fichier, $ligne);
});

set_exception_handler(static function (Throwable $e): void {
    gererExceptionFatale($e);
});
