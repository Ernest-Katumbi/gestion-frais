<?php
/**
 * Configuration de l'application — Institut des Oliviers.
 *
 * Copiez ce fichier en config/config.php puis adaptez les valeurs par défaut (XAMPP).
 * Chaque valeur peut aussi être fournie par une variable d'environnement du même nom
 * (hébergement en ligne, tests) : elle est alors prioritaire.
 * Le dossier config/ est hors du webroot (public/) : les secrets ne sont jamais servis.
 */
declare(strict_types=1);

/** Valeur d'une variable d'environnement, sinon la valeur par défaut. */
$env = static function (string $nom, string|int|bool $defaut): string|int|bool {
    $valeur = getenv($nom);
    if ($valeur === false || $valeur === '') {
        return $defaut;
    }
    return match (true) {
        is_bool($defaut) => in_array(strtolower($valeur), ['1', 'true', 'oui', 'yes', 'on'], true),
        is_int($defaut)  => (int) $valeur,
        default          => $valeur,
    };
};

// --- Application ------------------------------------------------------------
define('APP_NOM', 'Institut des Oliviers');
define('APP_VILLE', 'Kolwezi, RDC');
define('APP_MENTION', 'École conventionnée adventiste');
define('APP_ENV', $env('APP_ENV', 'development'));   // 'development' ou 'production'
define('APP_DEBUG', APP_ENV === 'development');       // détail des erreurs 500 (jamais en production)
define('APP_DEMO', $env('APP_DEMO', true));           // comptes de démonstration sur la page de connexion
// URL absolue du dossier public/ (sans barre finale) : liens, QR code des reçus, callback.
// En ligne sur Render, RENDER_EXTERNAL_URL est fournie automatiquement.
define('APP_URL', rtrim((string) $env('APP_URL', (string) $env('RENDER_EXTERNAL_URL', 'http://localhost/gestion-frais/public')), '/'));
define('APP_TIMEZONE', 'Africa/Lubumbashi');           // Kolwezi : UTC+2
// Derrière un proxy (Render) : utiliser X-Forwarded-For / X-Forwarded-Proto.
define('TRUST_PROXY', $env('TRUST_PROXY', false));

// --- Base de données (MariaDB de XAMPP ou MySQL hébergé) --------------------------
define('DB_HOST', $env('DB_HOST', '127.0.0.1'));
define('DB_PORT', $env('DB_PORT', 3306));
define('DB_NAME', $env('DB_NAME', 'gestion_frais'));
define('DB_USER', $env('DB_USER', 'root'));
define('DB_PASS', $env('DB_PASS', ''));
// Connexion chiffrée (obligatoire chez la plupart des hébergeurs MySQL) : chemin du certificat CA.
define('DB_SSL_CA', $env('DB_SSL_CA', ''));

// --- Dossiers de stockage (surchargés par les tests pour ne pas toucher aux données de démo) ---
define('RECUS_DIR', $env('RECUS_DIR', dirname(__DIR__) . '/recus'));          // reçus PDF, hors webroot
define('LOGS_DIR', $env('LOGS_DIR', dirname(__DIR__) . '/storage/logs'));    // app.log, mail.log

// --- Monnaie ----------------------------------------------------------------
// Devise de base, choisie à l'installation : frais, soldes et rapports sont tenus dans
// cette devise. (La changer après la saisie de données fausserait tous les montants.)
define('DEVISE', (string) $env('DEVISE', 'CDF'));
// Seconde devise acceptée pour les paiements : convertie dans la devise de base au taux
// du jour, saisi par le comptable dans l'écran « Taux de change ».
define('DEVISE_ETRANGERE', (string) $env('DEVISE_ETRANGERE', 'USD'));
// Présentation des devises : symbole affiché et nombre de décimales (le franc n'a pas de centimes).
define('DEVISES', [
    'CDF' => ['symbole' => 'FC',  'nom' => 'Franc congolais',  'decimales' => 0],
    'USD' => ['symbole' => 'USD', 'nom' => 'Dollar américain', 'decimales' => 2],
    'EUR' => ['symbole' => 'EUR', 'nom' => 'Euro',             'decimales' => 2],
]);

// --- Sécurité ---------------------------------------------------------------
define('LOGIN_MAX_TENTATIVES', 5);            // échecs autorisés…
define('LOGIN_BLOCAGE_MINUTES', 5);           // …avant un blocage de cette durée
define('SESSION_INACTIVITE_MINUTES', 30);     // déconnexion automatique après inactivité
// Clé HMAC servant à signer les URL de vérification des reçus (QR code). À changer !
define('RECU_SECRET', $env('RECU_SECRET', 'changez-moi-cle-recus-aleatoire-64-caracteres'));

// --- Paiement électronique --------------------------------------------------
define('PAYMENT_DRIVER', $env('PAYMENT_DRIVER', 'simulateur')); // 'simulateur' (sandbox locale) ou 'reel'
// Secret partagé avec la passerelle : signature HMAC-SHA256 des notifications (en-tête X-Signature). À changer !
define('PAYMENT_WEBHOOK_SECRET', $env('PAYMENT_WEBHOOK_SECRET', 'changez-moi-secret-passerelle'));

// --- E-mails ----------------------------------------------------------------
define('MAIL_DRIVER', $env('MAIL_DRIVER', 'log'));   // 'log' (storage/logs/mail.log) ou 'smtp' (PHPMailer)
define('MAIL_FROM', $env('MAIL_FROM', 'no-reply@oliviers.cd'));
define('MAIL_FROM_NOM', 'Institut des Oliviers');
define('SMTP_HOST', $env('SMTP_HOST', ''));
define('SMTP_PORT', $env('SMTP_PORT', 587));
define('SMTP_USER', $env('SMTP_USER', ''));
define('SMTP_PASS', $env('SMTP_PASS', ''));
