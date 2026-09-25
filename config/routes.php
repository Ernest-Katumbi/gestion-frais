<?php
/**
 * Table des routes : méthode HTTP + chemin → [Contrôleur, action].
 * Le contrôle des rôles est fait dans chaque action avec exigerRole().
 *
 * @var Router $router
 */
declare(strict_types=1);

// Authentification
$router->get('/',             [AuthController::class, 'accueil']);
$router->get('/connexion',    [AuthController::class, 'formulaire']);
$router->post('/connexion',   [AuthController::class, 'connexion']);
$router->post('/deconnexion', [AuthController::class, 'deconnexion']);

// Administrateur
$router->get('/admin',                           [UserController::class, 'tableauDeBord']);
$router->get('/utilisateurs',                    [UserController::class, 'index']);
$router->get('/utilisateurs/nouveau',            [UserController::class, 'creer']);
$router->post('/utilisateurs',                   [UserController::class, 'enregistrer']);
$router->get('/utilisateurs/{id}/modifier',      [UserController::class, 'modifier']);
$router->post('/utilisateurs/{id}',              [UserController::class, 'mettreAJour']);
$router->post('/utilisateurs/{id}/statut',       [UserController::class, 'basculerStatut']);
$router->post('/utilisateurs/{id}/supprimer',    [UserController::class, 'supprimer']);

$router->get('/classes',                   [ClasseController::class, 'index']);
$router->get('/classes/nouveau',           [ClasseController::class, 'creer']);
$router->post('/classes',                  [ClasseController::class, 'enregistrer']);
$router->get('/classes/{id}/modifier',     [ClasseController::class, 'modifier']);
$router->post('/classes/{id}',             [ClasseController::class, 'mettreAJour']);
$router->post('/classes/{id}/supprimer',   [ClasseController::class, 'supprimer']);

$router->get('/eleves',                    [EleveController::class, 'index']);
$router->get('/eleves/nouveau',            [EleveController::class, 'creer']);
$router->get('/eleves/parent',             [EleveController::class, 'rechercherParent']);
$router->post('/eleves',                   [EleveController::class, 'enregistrer']);
$router->get('/eleves/{id}',               [EleveController::class, 'afficher']);
$router->get('/eleves/{id}/modifier',      [EleveController::class, 'modifier']);
$router->post('/eleves/{id}',              [EleveController::class, 'mettreAJour']);
$router->post('/eleves/{id}/supprimer',    [EleveController::class, 'supprimer']);

// Comptable
$router->get('/comptable', [RapportController::class, 'tableauDeBord']);

// Parent
$router->get('/parent', [ParentController::class, 'accueil']);

// Mon compte (tous les rôles)
$router->get('/compte',               [AuthController::class, 'compte']);
$router->post('/compte/mot-de-passe', [AuthController::class, 'changerMotDePasse']);
