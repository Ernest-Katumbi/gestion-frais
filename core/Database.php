<?php
declare(strict_types=1);

/**
 * Point d'accès unique à la base de données (patron Singleton).
 * Toutes les requêtes de l'application passent par cette connexion PDO
 * et utilisent exclusivement des requêtes préparées.
 */
final class Database
{
    private static ?PDO $connexion = null;

    public static function get(): PDO
    {
        if (self::$connexion === null) {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
            self::$connexion = new PDO($dsn, DB_USER, DB_PASS, self::options());
            // Même fuseau horaire pour PHP et MySQL (CURRENT_TIMESTAMP cohérent avec date()).
            self::$connexion->exec("SET time_zone = '" . date('P') . "'");
        }
        return self::$connexion;
    }

    /** Connexion au serveur sans base sélectionnée (création de la base à l'installation). */
    public static function serveur(): PDO
    {
        return new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT), DB_USER, DB_PASS, self::options());
    }

    /** Options PDO communes : exceptions, vraies requêtes préparées, TLS si configuré. */
    private static function options(): array
    {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // vraies requêtes préparées côté serveur
        ];
        // Base hébergée : connexion chiffrée (TLS) avec vérification du certificat du serveur.
        if (DB_SSL_CA !== '') {
            $options[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }
        return $options;
    }

    /** Remplace la connexion (utilisé par les tests sur une base dédiée). */
    public static function definir(?PDO $connexion): void
    {
        self::$connexion = $connexion;
    }
}
