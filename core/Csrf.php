<?php
declare(strict_types=1);

/**
 * Protection contre la falsification de requêtes inter-sites (CSRF).
 * Un jeton aléatoire est lié à la session ; chaque requête POST doit le renvoyer.
 */
final class Csrf
{
    private const CLE = '_csrf';

    public static function jeton(): string
    {
        if (empty($_SESSION[self::CLE])) {
            $_SESSION[self::CLE] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CLE];
    }

    /** Champ caché à placer dans chaque formulaire POST. */
    public static function champ(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::jeton()) . '">';
    }

    /** Vérifie le jeton reçu (champ de formulaire ou en-tête X-CSRF-Token pour les appels JS). */
    public static function verifier(): bool
    {
        $recu = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $attendu = $_SESSION[self::CLE] ?? '';
        return is_string($recu) && $attendu !== '' && hash_equals($attendu, $recu);
    }
}
