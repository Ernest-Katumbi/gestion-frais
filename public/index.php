<?php
/**
 * Point d'entrée unique de l'application (contrôleur frontal).
 * Toutes les URL sont réécrites vers ce fichier par public/.htaccess.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/core/bootstrap.php';

// En-têtes de sécurité HTTP.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");

Session::demarrer();

$router = Router::instance();
require RACINE . '/config/routes.php';

try {
    $router->dispatcher($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
} catch (HttpException $e) {
    afficherErreur($e->getCode(), $e->getMessage());
} catch (Throwable $e) {
    gererExceptionFatale($e);
}
