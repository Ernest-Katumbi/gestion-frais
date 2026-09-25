# Gestion des frais scolaires — Institut des Oliviers

Prototype d'application web de gestion des frais scolaires avec paiement électronique
(Institut des Oliviers, Kolwezi, RDC). PHP 8.1+ natif (MVC), MySQL/MariaDB via PDO,
HTML/CSS/JS sans framework ni CDN.

> Document provisoire (itérations 1 et 2). La procédure complète d'installation sous XAMPP,
> les tests et le cron seront détaillés à l'itération 5.

## Installation rapide (XAMPP)

1. Placer le projet dans `C:\xampp\htdocs\gestion-frais` (ou déclarer un alias Apache
   vers son dossier, avec `AllowOverride All`).
2. Installer les dépendances : `composer install`
3. Copier `config/config.example.php` en `config/config.php` et adapter
   `DB_PORT`, `APP_URL` et les deux secrets (`RECU_SECRET`, `PAYMENT_WEBHOOK_SECRET`).
4. Démarrer Apache et MySQL dans le panneau XAMPP, puis créer la base et les données
   de démonstration : `php database/seed.php`
5. Ouvrir <http://localhost/gestion-frais/public/>

## Comptes de démonstration

Mot de passe commun : `Demo@2026`

| Rôle           | E-mail                  |
|----------------|-------------------------|
| Administrateur | `admin@oliviers.cd`     |
| Comptable      | `comptable@oliviers.cd` |
| Parent         | `parent@oliviers.cd`    |

## Arborescence

| Dossier        | Contenu                                                           |
|----------------|-------------------------------------------------------------------|
| `config/`      | configuration (hors webroot) et table des routes                  |
| `core/`        | Database, Router, Session, Csrf, View, Controller, helpers        |
| `controllers/` | contrôleurs MVC                                                   |
| `models/`      | accès aux données (requêtes préparées PDO)                        |
| `services/`    | passerelle de paiement, reçus, notifications                      |
| `views/`       | gabarits et vues PHP                                              |
| `public/`      | seul dossier exposé : `index.php`, ressources statiques           |
| `database/`    | schéma SQL et script de données de démonstration                  |
| `storage/logs` | `app.log` (erreurs), `mail.log` (e-mails simulés)                 |
