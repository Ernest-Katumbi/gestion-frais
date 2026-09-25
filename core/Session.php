<?php
declare(strict_types=1);

/**
 * Gestion de la session PHP : cookie sécurisé, expiration après inactivité
 * et messages « flash » (valables pour la requête suivante uniquement).
 */
final class Session
{
    private const CLE_FLASH = '_flash';

    /** Données flash de la requête courante, lues une seule fois. */
    private static ?array $flashCourant = null;

    public static function demarrer(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        ini_set('session.use_strict_mode', '1');   // refuse les identifiants de session inventés
        ini_set('session.use_only_cookies', '1');
        session_name('OLIVIERS_SID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => cheminBase() . '/',
            'secure'   => $https,   // cookie transmis uniquement en HTTPS quand il est disponible
            'httponly' => true,     // inaccessible au JavaScript
            'samesite' => 'Lax',
        ]);
        session_start();

        // Déconnexion automatique après une période d'inactivité.
        $maintenant = time();
        $derniere = $_SESSION['_derniere_activite'] ?? $maintenant;
        if (isset($_SESSION['id_utilisateur']) && $maintenant - $derniere > SESSION_INACTIVITE_MINUTES * 60) {
            self::detruire();
            session_id(session_create_id());
            session_start();
            self::message('info', 'Votre session a expiré après une période d\'inactivité. Veuillez vous reconnecter.');
        }
        $_SESSION['_derniere_activite'] = $maintenant;
    }

    public static function get(string $cle, mixed $defaut = null): mixed
    {
        return $_SESSION[$cle] ?? $defaut;
    }

    public static function set(string $cle, mixed $valeur): void
    {
        $_SESSION[$cle] = $valeur;
    }

    public static function supprimer(string $cle): void
    {
        unset($_SESSION[$cle]);
    }

    /** Nouvel identifiant de session (à la connexion) : protège contre la fixation de session. */
    public static function regenerer(): void
    {
        session_regenerate_id(true);
    }

    public static function detruire(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 3600,
                'path'     => $p['path'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'],
            ]);
            session_destroy();
        }
        self::$flashCourant = null;
    }

    // --- Messages flash ------------------------------------------------------

    /** Ajoute un message (toast) affiché à la prochaine page : succes, erreur, info, avertissement. */
    public static function message(string $type, string $texte): void
    {
        $_SESSION[self::CLE_FLASH]['messages'][] = ['type' => $type, 'texte' => $texte];
    }

    /** Stocke une donnée flash quelconque (anciennes saisies, erreurs de validation…). */
    public static function flash(string $cle, mixed $valeur): void
    {
        $_SESSION[self::CLE_FLASH][$cle] = $valeur;
    }

    /**
     * Lit une donnée flash. Au premier appel, les données flash sont retirées
     * de la session : elles ne survivent pas à la requête qui les affiche.
     */
    public static function lireFlash(string $cle, mixed $defaut = null): mixed
    {
        if (self::$flashCourant === null) {
            self::$flashCourant = $_SESSION[self::CLE_FLASH] ?? [];
            unset($_SESSION[self::CLE_FLASH]);
        }
        return self::$flashCourant[$cle] ?? $defaut;
    }
}
