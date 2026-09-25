<?php
/**
 * Fonctions utilitaires globales : échappement, URL, formatage des montants et
 * des dates, contrôle d'accès, journalisation.
 */
declare(strict_types=1);

// --- Affichage ---------------------------------------------------------------

/** Échappe une valeur pour l'afficher sans risque dans du HTML (protection XSS). */
function e(mixed $valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Montant formaté avec la devise : 1 250,00 USD (espaces insécables). */
function formaterMontant(float|string|null $montant): string
{
    return number_format((float) $montant, 2, ',', "\u{00A0}") . "\u{00A0}" . DEVISE;
}

/** Date au format 24/09/2026. */
function formaterDate(?string $date): string
{
    if ($date === null || $date === '') {
        return '—';
    }
    return (new DateTimeImmutable($date))->format('d/m/Y');
}

/** Date et heure au format 24/09/2026 à 14:05. */
function formaterDateHeure(?string $date): string
{
    if ($date === null || $date === '') {
        return '—';
    }
    return (new DateTimeImmutable($date))->format('d/m/Y \à H:i');
}

function dateValide(string $date): bool
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

/** Initiales d'un nom (avatar) : « Mbuyi Kalala » → « MK ». */
function initiales(string $nom): string
{
    $mots = preg_split('/\s+/', trim($nom)) ?: [];
    $lettres = array_map(static fn(string $m): string => mb_strtoupper(mb_substr($m, 0, 1)), array_slice($mots, 0, 2));
    return implode('', $lettres);
}

/** Badge coloré d'un statut de frais (impayé, partiel, payé) ou de paiement (en attente, réussi, échoué). */
function badgeStatut(string $statut): string
{
    $libelles = Frais::STATUTS + Paiement::STATUTS;
    return '<span class="badge badge-' . e($statut) . '">' . e($libelles[$statut] ?? $statut) . '</span>';
}

/** Part payée d'un montant, en pourcentage entier (0 à 100). */
function pourcentagePaye(float|string $paye, float|string $du): int
{
    return (float) $du > 0 ? (int) min(100, floor((float) $paye / (float) $du * 100)) : 0;
}

/** Libellé d'une échéance relative à aujourd'hui : « en retard de 5 jours », « dans 3 jours »… */
function libelleEcheance(string $echeance): string
{
    $jours = (int) (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($echeance))->format('%r%a');
    return match (true) {
        $jours < -1 => 'en retard de ' . abs($jours) . ' jours',
        $jours === -1 => 'en retard d\'un jour',
        $jours === 0 => 'aujourd\'hui',
        $jours === 1 => 'demain',
        default => 'dans ' . $jours . ' jours',
    };
}

function libelleRole(string $role): string
{
    return User::ROLES[$role] ?? $role;
}

/**
 * Icône SVG inline (jeu d'icônes de type Lucide stocké dans public/assets/icons/).
 * Les fichiers sont lus une seule fois par requête.
 */
function icone(string $nom, string $classe = ''): string
{
    static $cache = [];
    if (!isset($cache[$nom])) {
        $fichier = RACINE . '/public/assets/icons/' . basename($nom) . '.svg';
        $cache[$nom] = is_file($fichier) ? trim((string) file_get_contents($fichier)) : '';
    }
    $classes = trim('icon ' . $classe);
    return str_replace('<svg ', '<svg class="' . e($classes) . '" aria-hidden="true" focusable="false" ', $cache[$nom]);
}

// --- URL et navigation -------------------------------------------------------

/** Chemin de base de l'application, ex. « /gestion-frais/public ». */
function cheminBase(): string
{
    return rtrim((string) parse_url(APP_URL, PHP_URL_PATH), '/');
}

/** URL interne : url('/utilisateurs') → /gestion-frais/public/utilisateurs. */
function url(string $chemin = '/', array $parametres = []): string
{
    $url = cheminBase() . '/' . ltrim($chemin, '/');
    if ($parametres !== []) {
        $url .= '?' . http_build_query($parametres);
    }
    return $url;
}

/** URL absolue (liens envoyés par e-mail, QR codes, callback de la passerelle). */
function urlAbsolue(string $chemin = '/', array $parametres = []): string
{
    $origine = preg_replace('#^(https?://[^/]+).*$#', '$1', APP_URL);
    return $origine . url($chemin, $parametres);
}

/** URL d'un fichier statique, avec numéro de version pour éviter le cache périmé. */
function asset(string $chemin): string
{
    $fichier = RACINE . '/public/assets/' . ltrim($chemin, '/');
    $version = is_file($fichier) ? (string) filemtime($fichier) : '1';
    return url('/assets/' . ltrim($chemin, '/')) . '?v=' . $version;
}

/** Chemin de la requête courante, ex. « /utilisateurs ». */
function cheminCourant(): string
{
    return Router::cheminDepuisUri($_SERVER['REQUEST_URI'] ?? '/');
}

function rediriger(string $chemin): never
{
    header('Location: ' . (str_starts_with($chemin, 'http') ? $chemin : url($chemin)));
    exit;
}

/** Interrompt la requête avec une page d'erreur HTTP (403, 404…). */
function abort(int $code, string $message = ''): never
{
    throw new HttpException($message, $code);
}

// --- Formulaires ---------------------------------------------------------------

function csrf_champ(): string
{
    return Csrf::champ();
}

/** Ancienne saisie d'un champ (après une erreur de validation), sinon la valeur par défaut. */
function old(string $champ, mixed $defaut = ''): string
{
    $saisie = Session::lireFlash('old', []);
    return (string) ($saisie[$champ] ?? $defaut);
}

/** Message d'erreur de validation d'un champ, ou null. */
function erreur(string $champ): ?string
{
    return Session::lireFlash('erreurs', [])[$champ] ?? null;
}

/** Échappe les caractères spéciaux de LIKE et entoure de %…%. */
function motifLike(string $texte): string
{
    return '%' . addcslashes($texte, '%_\\') . '%';
}

/** Mot de passe provisoire lisible (sans caractères ambigus : 0/O, 1/l/I). */
function genererMotDePasseProvisoire(): string
{
    $lettres = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    $chiffres = '23456789';
    $mdp = '';
    for ($i = 0; $i < 6; $i++) {
        $mdp .= $lettres[random_int(0, strlen($lettres) - 1)];
    }
    for ($i = 0; $i < 4; $i++) {
        $mdp .= $chiffres[random_int(0, strlen($chiffres) - 1)];
    }
    return 'Oliv-' . $mdp;
}

// --- Authentification et contrôle d'accès -------------------------------------

/** Utilisateur connecté (relu en base à chaque requête : un compte désactivé perd l'accès immédiatement). */
function utilisateur(): ?array
{
    static $charge = false;
    static $utilisateur = null;
    if (!$charge) {
        $charge = true;
        $id = Session::get('id_utilisateur');
        if ($id !== null) {
            $utilisateur = User::trouver((int) $id);
            if ($utilisateur === null || $utilisateur['statut'] !== 'actif') {
                Session::detruire();
                $utilisateur = null;
            }
        }
    }
    return $utilisateur;
}

/**
 * Exige que l'utilisateur soit connecté et possède l'un des rôles indiqués.
 * Sans rôle précisé, une simple connexion suffit.
 * - non connecté : redirection vers la page de connexion ;
 * - rôle insuffisant : page 403.
 */
function exigerRole(string ...$roles): array
{
    $utilisateur = utilisateur();
    if ($utilisateur === null) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            Session::set('apres_connexion', cheminCourant());
        }
        Session::message('info', 'Veuillez vous connecter pour continuer.');
        rediriger('/connexion');
    }
    if ($roles !== [] && !in_array($utilisateur['role'], $roles, true)) {
        abort(403, 'Cette page est réservée à un autre profil d\'utilisateur.');
    }
    return $utilisateur;
}

/** Page d'accueil de chaque rôle. */
function accueilDuRole(string $role): string
{
    return match ($role) {
        'admin'     => '/admin',
        'comptable' => '/comptable',
        default     => '/parent',
    };
}

// --- Journalisation et erreurs ---------------------------------------------------

/** Écrit une ligne horodatée dans storage/logs/{canal}.log (app, mail…). */
function journaliser(string $canal, string $message): void
{
    $ligne = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents(RACINE . '/storage/logs/' . basename($canal) . '.log', $ligne, FILE_APPEND | LOCK_EX);
}

/** Affiche la page d'erreur HTTP correspondante (ou du JSON pour les appels JavaScript). */
function afficherErreur(int $code, string $message = '', ?Throwable $exception = null): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($code);
    }
    $accepte = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (str_contains($accepte, 'application/json')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erreur' => $message !== '' ? $message : 'Erreur ' . $code], JSON_UNESCAPED_UNICODE);
        return;
    }
    $vue = in_array($code, [403, 404, 500], true) ? (string) $code : '500';
    try {
        echo View::rendre('errors/' . $vue, [
            'titre'     => 'Erreur ' . $code,
            'message'   => $message,
            'exception' => APP_DEBUG ? $exception : null,
        ], 'layouts/simple');
    } catch (Throwable) {
        echo 'Erreur ' . $code;
    }
}

/** Dernier filet de sécurité : journalise l'exception et affiche une page 500 sans trace en production. */
function gererExceptionFatale(Throwable $e): void
{
    journaliser('app', get_class($e) . ' : ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ")\n" . $e->getTraceAsString());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Erreur : ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    afficherErreur(500, '', $e);
}
