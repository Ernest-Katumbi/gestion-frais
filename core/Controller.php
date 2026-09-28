<?php
declare(strict_types=1);

/**
 * Classe de base des contrôleurs : rendu des vues, redirections, réponses JSON
 * et validation serveur des saisies.
 */
abstract class Controller
{
    protected function vue(string $vue, array $donnees = [], ?string $layout = 'layouts/app'): void
    {
        echo View::rendre($vue, $donnees, $layout);
    }

    protected function rediriger(string $chemin): never
    {
        rediriger($chemin);
    }

    protected function json(array $donnees, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Valeur texte d'un champ POST, nettoyée des espaces superflus. */
    protected function post(string $cle, string $defaut = ''): string
    {
        $valeur = $_POST[$cle] ?? $defaut;
        return is_string($valeur) ? trim($valeur) : $defaut;
    }

    /** Valeur texte d'un paramètre GET. */
    protected function query(string $cle, string $defaut = ''): string
    {
        $valeur = $_GET[$cle] ?? $defaut;
        return is_string($valeur) ? trim($valeur) : $defaut;
    }

    /** Numéro de page demandé (≥ 1). */
    protected function page(): int
    {
        return max(1, (int) $this->query('page', '1'));
    }

    /**
     * Revient au formulaire en conservant la saisie (hors mots de passe)
     * et en affichant les erreurs sous les champs concernés.
     */
    protected function retourAvecErreurs(string $chemin, array $erreurs, string $message = 'Veuillez corriger les champs signalés.'): never
    {
        $saisie = $_POST;
        unset($saisie['_csrf'], $saisie['mot_de_passe'], $saisie['mot_de_passe_confirmation']);
        Session::flash('old', $saisie);
        Session::flash('erreurs', $erreurs);
        Session::message('erreur', $message);
        rediriger($chemin);
    }

    /**
     * Validation serveur. Règles séparées par « | » :
     * requis, email, min:n, max:n, dans:a,b,c, telephone, date, montant[:DEVISE], entier, mot_de_passe.
     * Renvoie un tableau champ => premier message d'erreur.
     */
    /** Montant positif avec au plus le nombre de décimales de la devise (le franc n'en a pas). */
    private static function montantValide(string $valeur, string $devise): ?string
    {
        $decimales = decimalesDevise($devise);
        $motif = $decimales > 0 ? '/^\d+([.,]\d{1,' . $decimales . '})?$/' : '/^\d+$/';
        if (preg_match($motif, $valeur) && (float) str_replace(',', '.', $valeur) > 0) {
            return null;
        }
        return $decimales > 0
            ? "Montant invalide (nombre positif, $decimales décimales au plus)."
            : 'Montant invalide : nombre entier positif en ' . symboleDevise($devise) . '.';
    }

    protected function valider(array $donnees, array $regles): array
    {
        $erreurs = [];
        foreach ($regles as $champ => $liste) {
            $valeur = trim((string) ($donnees[$champ] ?? ''));
            foreach (explode('|', $liste) as $regle) {
                [$nom, $param] = array_pad(explode(':', $regle, 2), 2, null);
                if ($nom !== 'requis' && $valeur === '') {
                    continue; // champ facultatif laissé vide
                }
                $message = match ($nom) {
                    'requis'       => $valeur === '' ? 'Ce champ est obligatoire.' : null,
                    'email'        => filter_var($valeur, FILTER_VALIDATE_EMAIL) ? null : 'Adresse e-mail invalide.',
                    'min'          => mb_strlen($valeur) >= (int) $param ? null : "Au moins $param caractères.",
                    'max'          => mb_strlen($valeur) <= (int) $param ? null : "Au plus $param caractères.",
                    'dans'         => in_array($valeur, explode(',', (string) $param), true) ? null : 'Valeur non autorisée.',
                    'telephone'    => preg_match('/^\+?[0-9 ]{9,20}$/', $valeur) ? null : 'Numéro invalide (ex. +243 97 123 4567).',
                    'date'         => dateValide($valeur) ? null : 'Date invalide.',
                    'montant'      => self::montantValide($valeur, $param ?: DEVISE),
                    'entier'       => ctype_digit($valeur) ? null : 'Nombre entier attendu.',
                    'mot_de_passe' => preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $valeur)
                                        ? null : 'Au moins 8 caractères, dont une lettre et un chiffre.',
                    default        => throw new LogicException("Règle de validation inconnue : $nom"),
                };
                if ($message !== null) {
                    $erreurs[$champ] = $message;
                    break;
                }
            }
        }
        return $erreurs;
    }
}
