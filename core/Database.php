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
            self::$connexion = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // vraies requêtes préparées côté serveur
            ]);
            // Même fuseau horaire pour PHP et MySQL (CURRENT_TIMESTAMP cohérent avec date()).
            self::$connexion->exec("SET time_zone = '" . date('P') . "'");
        }
        return self::$connexion;
    }

    /** Remplace la connexion (utilisé par les tests sur une base dédiée). */
    public static function definir(?PDO $connexion): void
    {
        self::$connexion = $connexion;
    }
}
