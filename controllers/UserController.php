<?php
declare(strict_types=1);

/**
 * Gestion des utilisateurs (administrateur) et tableau de bord de l'administrateur.
 */
final class UserController extends Controller
{
    private const PAR_PAGE = 12;

    public function tableauDeBord(): void
    {
        exigerRole('admin');
        $this->vue('admin/tableau_de_bord', [
            'titre'          => 'Tableau de bord',
            'parRole'        => User::compterParRole(),
            'inactifs'       => User::compterInactifs(),
            'nbEleves'       => Eleve::compter(),
            'classes'        => Classe::toutes(),
            'derniersEleves' => Eleve::derniers(6),
            'derniers'       => User::derniers(4),
        ]);
    }

    public function index(): void
    {
        exigerRole('admin');
        $filtres = [
            'q'      => $this->query('q'),
            'role'   => $this->query('role'),
            'statut' => $this->query('statut'),
        ];
        $page = $this->page();
        $resultat = User::lister($filtres['q'], $filtres['role'], $filtres['statut'], $page, self::PAR_PAGE);

        $this->vue('admin/utilisateurs', [
            'titre'        => 'Utilisateurs',
            'fil'          => [['Utilisateurs', null]],
            'utilisateurs' => $resultat['lignes'],
            'filtres'      => $filtres,
            'pagination'   => ['page' => $page, 'parPage' => self::PAR_PAGE, 'total' => $resultat['total']],
            'provisoire'   => Session::lireFlash('mot_de_passe_provisoire'),
        ]);
    }

    public function creer(): void
    {
        exigerRole('admin');
        $this->vue('admin/utilisateur_form', [
            'titre' => 'Nouvel utilisateur',
            'fil'   => [['Utilisateurs', '/utilisateurs'], ['Nouvel utilisateur', null]],
            'u'     => null,
        ]);
    }

    public function enregistrer(): void
    {
        exigerRole('admin');
        $donnees = $this->donneesFormulaire();
        $erreurs = $this->validerFormulaire($donnees, null);
        if ($erreurs) {
            $this->retourAvecErreurs('/utilisateurs/nouveau', $erreurs);
        }

        // Sans mot de passe saisi, un mot de passe provisoire est généré et communiqué une seule fois.
        $provisoire = $donnees['mot_de_passe'] === '' ? genererMotDePasseProvisoire() : null;
        $donnees['mot_de_passe'] = $provisoire ?? $donnees['mot_de_passe'];
        $donnees['statut'] = 'actif';
        User::creer($donnees);

        if ($provisoire !== null) {
            Notificateur::compteCree($donnees, $provisoire);
            Session::flash('mot_de_passe_provisoire', ['nom' => $donnees['nom'], 'email' => $donnees['email'], 'mot_de_passe' => $provisoire]);
        }
        Session::message('succes', 'Le compte de ' . $donnees['nom'] . ' a été créé.');
        $this->rediriger('/utilisateurs');
    }

    public function modifier(int $id): void
    {
        exigerRole('admin');
        $u = User::trouver($id) ?? abort(404, 'Cet utilisateur n\'existe pas.');
        $this->vue('admin/utilisateur_form', [
            'titre' => 'Modifier un utilisateur',
            'fil'   => [['Utilisateurs', '/utilisateurs'], [$u['nom'], null]],
            'u'     => $u,
        ]);
    }

    public function mettreAJour(int $id): void
    {
        $admin = exigerRole('admin');
        $u = User::trouver($id) ?? abort(404, 'Cet utilisateur n\'existe pas.');
        $donnees = $this->donneesFormulaire();
        $donnees['statut'] = $this->post('statut', $u['statut']);
        $erreurs = $this->validerFormulaire($donnees, $u);

        // Garde-fous : l'administrateur ne peut pas se retirer ses propres droits,
        // et l'Institut doit toujours garder au moins un administrateur actif.
        $retireAdmin = $u['role'] === 'admin' && $u['statut'] === 'actif'
            && ($donnees['role'] !== 'admin' || $donnees['statut'] !== 'actif');
        if ($retireAdmin && (int) $u['id_utilisateur'] === (int) $admin['id_utilisateur']) {
            $erreurs['role'] = 'Vous ne pouvez pas retirer vos propres droits d\'administrateur ni désactiver votre compte.';
        } elseif ($retireAdmin && User::compterAdminsActifs() <= 1) {
            $erreurs['role'] = 'Il doit rester au moins un administrateur actif.';
        }
        if ($erreurs) {
            $this->retourAvecErreurs("/utilisateurs/$id/modifier", $erreurs);
        }

        User::modifier($id, $donnees);
        if ($donnees['mot_de_passe'] !== '') {
            User::changerMotDePasse($id, $donnees['mot_de_passe']);
        }
        Session::message('succes', 'Les informations de ' . $donnees['nom'] . ' ont été enregistrées.');
        $this->rediriger('/utilisateurs');
    }

    /** Active ou désactive un compte. */
    public function basculerStatut(int $id): void
    {
        $admin = exigerRole('admin');
        $u = User::trouver($id) ?? abort(404, 'Cet utilisateur n\'existe pas.');
        $nouveau = $u['statut'] === 'actif' ? 'inactif' : 'actif';

        if ($nouveau === 'inactif' && $erreur = $this->refusRetraitAdmin($u, $admin)) {
            Session::message('erreur', $erreur);
            $this->rediriger('/utilisateurs');
        }
        User::changerStatut($id, $nouveau);
        Session::message('succes', $nouveau === 'actif'
            ? 'Le compte de ' . $u['nom'] . ' est de nouveau actif.'
            : 'Le compte de ' . $u['nom'] . ' a été désactivé : il ne peut plus se connecter.');
        $this->rediriger('/utilisateurs');
    }

    /**
     * Suppression protégée : un utilisateur ayant un historique (enfants inscrits,
     * paiements encaissés, notifications) est désactivé au lieu d'être supprimé.
     */
    public function supprimer(int $id): void
    {
        $admin = exigerRole('admin');
        $u = User::trouver($id) ?? abort(404, 'Cet utilisateur n\'existe pas.');

        if ($erreur = $this->refusRetraitAdmin($u, $admin)) {
            Session::message('erreur', $erreur);
            $this->rediriger('/utilisateurs');
        }
        if (User::aUnHistorique($id)) {
            User::changerStatut($id, 'inactif');
            Session::message('info', $u['nom'] . ' possède un historique (élèves, paiements ou notifications) : le compte a été désactivé plutôt que supprimé.');
        } else {
            User::supprimer($id);
            Session::message('succes', 'Le compte de ' . $u['nom'] . ' a été supprimé.');
        }
        $this->rediriger('/utilisateurs');
    }

    // --- Notifications de l'utilisateur connecté (tous les rôles) ------------------------

    public function notifications(): void
    {
        $moi = exigerRole();
        $page = $this->page();
        $resultat = Notification::lister((int) $moi['id_utilisateur'], $page, 15);
        $this->vue('notifications', [
            'titre'         => 'Notifications',
            'fil'           => [['Notifications', null]],
            'notifications' => $resultat['lignes'],
            'nonLues'       => Notification::compterNonLues((int) $moi['id_utilisateur']),
            'pagination'    => ['page' => $page, 'parPage' => 15, 'total' => $resultat['total']],
        ]);
    }

    public function marquerLu(int $id): void
    {
        $moi = exigerRole();
        // La condition sur id_utilisateur empêche de modifier la notification d'un autre.
        Notification::marquerLue($id, (int) $moi['id_utilisateur']);
        $this->rediriger($this->retourNotifications());
    }

    public function toutMarquerLu(): void
    {
        $moi = exigerRole();
        $n = Notification::toutMarquerLues((int) $moi['id_utilisateur']);
        Session::message('succes', $n > 0 ? "$n notification" . ($n > 1 ? 's marquées' : ' marquée') . ' comme lue' . ($n > 1 ? 's' : '') . '.' : 'Aucune notification non lue.');
        $this->rediriger($this->retourNotifications());
    }

    /** Page d'où vient la demande (liste des notifications par défaut), limitée aux chemins internes. */
    private function retourNotifications(): string
    {
        $retour = $this->post('retour');
        return preg_match('#^/[a-z0-9/_-]*$#i', $retour) ? $retour : '/notifications';
    }

    // --- Outils internes -------------------------------------------------------------

    private function donneesFormulaire(): array
    {
        return [
            'nom'          => $this->post('nom'),
            'email'        => mb_strtolower($this->post('email')),
            'role'         => $this->post('role'),
            'telephone'    => $this->post('telephone'),
            'adresse'      => $this->post('adresse'),
            'mot_de_passe' => (string) ($_POST['mot_de_passe'] ?? ''),
        ];
    }

    private function validerFormulaire(array $donnees, ?array $existant): array
    {
        $regles = [
            'nom'          => 'requis|min:2|max:100',
            'email'        => 'requis|email|max:100',
            'role'         => 'requis|dans:' . implode(',', array_keys(User::ROLES)),
            'telephone'    => 'telephone|max:20',
            'adresse'      => 'max:150',
            'mot_de_passe' => 'mot_de_passe',
        ];
        if ($existant !== null) {
            $regles['statut'] = 'requis|dans:actif,inactif';
        }
        $erreurs = $this->valider($donnees, $regles);
        if (!isset($erreurs['email']) && User::emailExiste($donnees['email'], $existant ? (int) $existant['id_utilisateur'] : null)) {
            $erreurs['email'] = 'Cette adresse e-mail est déjà utilisée par un autre compte.';
        }
        return $erreurs;
    }

    /** Message de refus si l'action retirerait le dernier administrateur actif ou l'administrateur lui-même. */
    private function refusRetraitAdmin(array $cible, array $admin): ?string
    {
        if ((int) $cible['id_utilisateur'] === (int) $admin['id_utilisateur']) {
            return 'Vous ne pouvez pas désactiver ni supprimer votre propre compte.';
        }
        if ($cible['role'] === 'admin' && $cible['statut'] === 'actif' && User::compterAdminsActifs() <= 1) {
            return 'Il doit rester au moins un administrateur actif.';
        }
        return null;
    }
}
