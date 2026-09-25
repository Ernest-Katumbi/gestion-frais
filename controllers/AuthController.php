<?php
declare(strict_types=1);

/**
 * Authentification : connexion, déconnexion, limitation des tentatives
 * et gestion de son propre mot de passe.
 */
final class AuthController extends Controller
{
    /** Empreinte factice : le temps de réponse est le même que l'e-mail existe ou non. */
    private const HASH_FACTICE = '$2y$10$kf0csSL8UrNS0adDnuJg9eAeNVtKUU50KBsY1O39ujmysN/MJlETW';

    /** Motif du dernier échec de login() : 'identifiants', 'inactif' ou 'bloque'. */
    private string $motifEchec = '';

    /** Redirige vers l'espace correspondant au rôle, ou vers la connexion. */
    public function accueil(): void
    {
        $utilisateur = utilisateur();
        $this->rediriger($utilisateur ? accueilDuRole($utilisateur['role']) : '/connexion');
    }

    public function formulaire(): void
    {
        if ($utilisateur = utilisateur()) {
            $this->rediriger(accueilDuRole($utilisateur['role']));
        }
        $this->vue('auth/connexion', ['titre' => 'Connexion'], 'layouts/simple');
    }

    /** Traitement du formulaire de connexion. */
    public function connexion(): void
    {
        $email = $this->post('email');
        $erreurs = $this->valider($_POST, ['email' => 'requis|email', 'mot_de_passe' => 'requis']);
        if ($erreurs) {
            $this->retourAvecErreurs('/connexion', $erreurs, 'Veuillez saisir votre e-mail et votre mot de passe.');
        }

        if (!$this->login($email, (string) ($_POST['mot_de_passe'] ?? ''))) {
            $attente = $this->secondesAvantDeblocage($email);
            $restantes = $this->tentativesRestantes($email);
            $message = match (true) {
                $attente > 0                    => sprintf('Trop de tentatives échouées. Réessayez dans %s.', self::duree($attente)),
                $this->motifEchec === 'inactif' => 'Ce compte est désactivé. Contactez l\'administration de l\'Institut.',
                $restantes <= 2                 => sprintf(
                    'E-mail ou mot de passe incorrect. Encore %d tentative%s avant un blocage de %d minutes.',
                    $restantes, $restantes > 1 ? 's' : '', LOGIN_BLOCAGE_MINUTES
                ),
                default                         => 'E-mail ou mot de passe incorrect.',
            };
            Session::flash('old', ['email' => $email]);
            Session::message('erreur', $message);
            $this->rediriger('/connexion');
        }

        $utilisateur = utilisateur() ?? User::trouverParEmail($email);
        $destination = Session::get('apres_connexion');
        Session::supprimer('apres_connexion');
        // Seuls les chemins internes sont acceptés (pas de redirection vers un autre site).
        if (!is_string($destination) || !preg_match('#^/[a-z0-9/_-]*$#i', $destination) || $destination === '/') {
            $destination = accueilDuRole($utilisateur['role']);
        }
        Session::message('succes', 'Bienvenue, ' . $utilisateur['nom'] . ' !');
        $this->rediriger($destination);
    }

    /**
     * Vérifie les identifiants et ouvre la session.
     * Renvoie false si l'e-mail ou le mot de passe est faux, si le compte est
     * désactivé ou si trop de tentatives ont échoué récemment.
     */
    public function login(string $email, string $motDePasse): bool
    {
        $email = mb_strtolower(trim($email));

        // 1. Limitation des tentatives : 5 échecs → attente de 5 minutes.
        if ($this->secondesAvantDeblocage($email) > 0) {
            $this->motifEchec = 'bloque';
            return false;
        }

        // 2. Vérification du mot de passe haché (password_verify, temps constant).
        $utilisateur = User::trouverParEmail($email);
        $motDePasseValide = password_verify($motDePasse, $utilisateur['mot_de_passe'] ?? self::HASH_FACTICE);
        if ($utilisateur === null || !$motDePasseValide) {
            $this->enregistrerEchec($email);
            $this->motifEchec = 'identifiants';
            return false;
        }
        if ($utilisateur['statut'] !== 'actif') {
            $this->motifEchec = 'inactif';
            return false;
        }

        // 3. Succès : nouvel identifiant de session (anti-fixation) et oubli des échecs.
        $this->effacerEchecs($email);
        if (password_needs_rehash($utilisateur['mot_de_passe'], PASSWORD_DEFAULT)) {
            User::changerMotDePasse((int) $utilisateur['id_utilisateur'], $motDePasse);
        }
        Session::regenerer();
        Session::set('id_utilisateur', (int) $utilisateur['id_utilisateur']);
        Session::set('role', $utilisateur['role']);
        return true;
    }

    public function deconnexion(): void
    {
        Session::detruire();
        session_id(session_create_id());
        Session::demarrer();
        Session::message('info', 'Vous êtes déconnecté. À bientôt !');
        $this->rediriger('/connexion');
    }

    // --- Mon compte --------------------------------------------------------------

    public function compte(): void
    {
        $utilisateur = exigerRole();
        $this->vue('auth/compte', [
            'titre'       => 'Mon compte',
            'utilisateur' => $utilisateur,
            'fil'         => [['Mon compte', null]],
        ]);
    }

    public function changerMotDePasse(): void
    {
        $utilisateur = exigerRole();
        $erreurs = $this->valider($_POST, [
            'mot_de_passe_actuel' => 'requis',
            'mot_de_passe'        => 'requis|mot_de_passe',
        ]);
        if (!isset($erreurs['mot_de_passe_actuel'])
            && !password_verify((string) $_POST['mot_de_passe_actuel'], $utilisateur['mot_de_passe'])) {
            $erreurs['mot_de_passe_actuel'] = 'Mot de passe actuel incorrect.';
        }
        if (!isset($erreurs['mot_de_passe']) && $_POST['mot_de_passe'] !== ($_POST['mot_de_passe_confirmation'] ?? '')) {
            $erreurs['mot_de_passe_confirmation'] = 'Les deux mots de passe ne correspondent pas.';
        }
        if ($erreurs) {
            unset($_POST['mot_de_passe_actuel']);
            $this->retourAvecErreurs('/compte', $erreurs);
        }
        User::changerMotDePasse((int) $utilisateur['id_utilisateur'], (string) $_POST['mot_de_passe']);
        Session::regenerer();
        Session::message('succes', 'Votre mot de passe a été modifié.');
        $this->rediriger('/compte');
    }

    // --- Limitation des tentatives de connexion -----------------------------------

    /** Secondes restantes avant de pouvoir réessayer (0 si non bloqué). */
    public function secondesAvantDeblocage(string $email): int
    {
        $requete = Database::get()->prepare(
            'SELECT COUNT(*) AS echecs, MAX(date_tentative) AS derniere
             FROM tentative_connexion
             WHERE email = ? AND adresse_ip = ? AND date_tentative > NOW() - INTERVAL ? MINUTE'
        );
        $requete->execute([mb_strtolower(trim($email)), self::adresseIp(), LOGIN_BLOCAGE_MINUTES]);
        $ligne = $requete->fetch();
        if ((int) $ligne['echecs'] < LOGIN_MAX_TENTATIVES) {
            return 0;
        }
        $fin = strtotime((string) $ligne['derniere']) + LOGIN_BLOCAGE_MINUTES * 60;
        return max(0, $fin - time());
    }

    private function tentativesRestantes(string $email): int
    {
        $requete = Database::get()->prepare(
            'SELECT COUNT(*) FROM tentative_connexion
             WHERE email = ? AND adresse_ip = ? AND date_tentative > NOW() - INTERVAL ? MINUTE'
        );
        $requete->execute([mb_strtolower(trim($email)), self::adresseIp(), LOGIN_BLOCAGE_MINUTES]);
        return max(0, LOGIN_MAX_TENTATIVES - (int) $requete->fetchColumn());
    }

    private function enregistrerEchec(string $email): void
    {
        Database::get()
            ->prepare('INSERT INTO tentative_connexion (email, adresse_ip) VALUES (?, ?)')
            ->execute([mb_substr($email, 0, 100), self::adresseIp()]);
        journaliser('app', "Échec de connexion pour « $email » depuis " . self::adresseIp());
    }

    private function effacerEchecs(string $email): void
    {
        Database::get()
            ->prepare('DELETE FROM tentative_connexion WHERE email = ? AND adresse_ip = ?')
            ->execute([$email, self::adresseIp()]);
    }

    private static function adresseIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
    }

    private static function duree(int $secondes): string
    {
        $minutes = intdiv($secondes, 60);
        $reste = $secondes % 60;
        if ($minutes === 0) {
            return "$reste s";
        }
        return $reste === 0 ? "$minutes min" : sprintf('%d min %02d s', $minutes, $reste);
    }
}
