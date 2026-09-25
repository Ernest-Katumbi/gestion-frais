<?php
/**
 * Configuration de l'application — Institut des Oliviers.
 *
 * Copiez ce fichier en config/config.php puis adaptez les valeurs.
 * Le dossier config/ est hors du webroot (public/) : les secrets ne sont
 * jamais servis par Apache.
 */
declare(strict_types=1);

// --- Application ------------------------------------------------------------
define('APP_NOM', 'Institut des Oliviers');
define('APP_VILLE', 'Kolwezi, RDC');
define('APP_ENV', 'development');            // 'development' ou 'production'
define('APP_DEBUG', APP_ENV === 'development'); // affiche le détail des erreurs 500 (jamais en production)
define('APP_DEMO', true);                     // affiche les comptes de démonstration sur la page de connexion
// URL absolue du dossier public/ (sans barre finale) : sert aux liens, au QR code et au callback.
define('APP_URL', 'http://localhost/gestion-frais/public');
define('APP_TIMEZONE', 'Africa/Lubumbashi');  // Kolwezi : UTC+2

// --- Base de données (MariaDB de XAMPP) --------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'gestion_frais');
define('DB_USER', 'root');
define('DB_PASS', '');

// --- Monnaie ----------------------------------------------------------------
define('DEVISE', 'USD');                      // affichée partout : 1 250,00 USD

// --- Sécurité ---------------------------------------------------------------
define('LOGIN_MAX_TENTATIVES', 5);            // échecs autorisés…
define('LOGIN_BLOCAGE_MINUTES', 5);           // …avant un blocage de cette durée
define('SESSION_INACTIVITE_MINUTES', 30);     // déconnexion automatique après inactivité
// Clé HMAC servant à signer les URL de vérification des reçus (QR code). À changer !
define('RECU_SECRET', 'changez-moi-cle-recus-aleatoire-64-caracteres');

// --- Paiement électronique --------------------------------------------------
define('PAYMENT_DRIVER', 'simulateur');       // 'simulateur' (sandbox locale) ou 'reel'
// Secret partagé avec la passerelle : signature HMAC-SHA256 des notifications (en-tête X-Signature). À changer !
define('PAYMENT_WEBHOOK_SECRET', 'changez-moi-secret-passerelle');

// --- E-mails ----------------------------------------------------------------
define('MAIL_DRIVER', 'log');                 // 'log' (storage/logs/mail.log) ou 'smtp' (PHPMailer)
define('MAIL_FROM', 'no-reply@oliviers.cd');
define('MAIL_FROM_NOM', 'Institut des Oliviers');
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
