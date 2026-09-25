<?php
declare(strict_types=1);

/**
 * Gestion des élèves (administrateur) : inscription avec rattachement au parent,
 * modification, fiche et suppression protégée.
 */
final class EleveController extends Controller
{
    private const PAR_PAGE = 15;

    public function index(): void
    {
        exigerRole('admin');
        $filtres = ['q' => $this->query('q'), 'classe' => (int) $this->query('classe', '0')];
        $page = $this->page();
        $resultat = Eleve::lister($filtres['q'], $filtres['classe'], $page, self::PAR_PAGE);

        $this->vue('admin/eleves', [
            'titre'      => 'Élèves',
            'fil'        => [['Élèves', null]],
            'eleves'     => $resultat['lignes'],
            'classes'    => Classe::options(),
            'filtres'    => $filtres,
            'pagination' => ['page' => $page, 'parPage' => self::PAR_PAGE, 'total' => $resultat['total']],
            'provisoire' => Session::lireFlash('mot_de_passe_provisoire'),
        ]);
    }

    public function afficher(int $id): void
    {
        exigerRole('admin');
        $eleve = Eleve::trouver($id) ?? abort(404, 'Cet élève n\'existe pas.');
        $freres = array_filter(
            Eleve::parParent((int) $eleve['id_parent']),
            static fn(array $e): bool => (int) $e['id_eleve'] !== $id
        );
        $this->vue('admin/eleve', [
            'titre'      => $eleve['prenom'] . ' ' . $eleve['nom'],
            'fil'        => [['Élèves', '/eleves'], [$eleve['prenom'] . ' ' . $eleve['nom'], null]],
            'eleve'      => $eleve,
            'freres'     => $freres,
            'supprimable' => !Eleve::aDesPaiements($id),
            'provisoire' => Session::lireFlash('mot_de_passe_provisoire'),
        ]);
    }

    public function creer(): void
    {
        exigerRole('admin');
        if (Classe::options() === []) {
            Session::message('avertissement', 'Créez d\'abord au moins une classe avant d\'inscrire un élève.');
            $this->rediriger('/classes/nouveau');
        }
        $this->vue('admin/eleve_form', [
            'titre'   => 'Inscrire un élève',
            'fil'     => [['Élèves', '/eleves'], ['Inscription', null]],
            'eleve'   => null,
            'classes' => Classe::options(),
            'matriculePropose' => Eleve::prochainMatricule(),
            'classePreselectionnee' => (int) $this->query('classe', '0'),
        ]);
    }

    public function enregistrer(): void
    {
        exigerRole('admin');
        $donnees = $this->donneesFormulaire();
        [$erreurs, $parent] = $this->validerFormulaire($donnees, null);
        if ($erreurs) {
            $this->retourAvecErreurs('/eleves/nouveau', $erreurs);
        }

        $resultat = $this->enregistrerAvecParent($donnees, $parent, null);
        Session::message('succes', sprintf(
            '%s %s est inscrit(e) en %s.',
            $donnees['prenom'], $donnees['nom'], Classe::trouver($donnees['id_classe'])['libelle']
        ));
        $this->rediriger('/eleves/' . $resultat);
    }

    public function modifier(int $id): void
    {
        exigerRole('admin');
        $eleve = Eleve::trouver($id) ?? abort(404, 'Cet élève n\'existe pas.');
        $this->vue('admin/eleve_form', [
            'titre'   => 'Modifier un élève',
            'fil'     => [['Élèves', '/eleves'], [$eleve['prenom'] . ' ' . $eleve['nom'], '/eleves/' . $id], ['Modifier', null]],
            'eleve'   => $eleve,
            'classes' => Classe::options(),
            'matriculePropose' => $eleve['matricule'],
            'classePreselectionnee' => (int) $eleve['id_classe'],
        ]);
    }

    public function mettreAJour(int $id): void
    {
        exigerRole('admin');
        Eleve::trouver($id) ?? abort(404, 'Cet élève n\'existe pas.');
        $donnees = $this->donneesFormulaire();
        [$erreurs, $parent] = $this->validerFormulaire($donnees, $id);
        if ($erreurs) {
            $this->retourAvecErreurs("/eleves/$id/modifier", $erreurs);
        }
        $this->enregistrerAvecParent($donnees, $parent, $id);
        Session::message('succes', 'La fiche de ' . $donnees['prenom'] . ' ' . $donnees['nom'] . ' a été mise à jour.');
        $this->rediriger('/eleves/' . $id);
    }

    /** Suppression protégée : refusée si l'élève a des paiements. */
    public function supprimer(int $id): void
    {
        exigerRole('admin');
        $eleve = Eleve::trouver($id) ?? abort(404, 'Cet élève n\'existe pas.');
        $nom = $eleve['prenom'] . ' ' . $eleve['nom'];
        if (Eleve::aDesPaiements($id)) {
            Session::message('erreur', "Impossible de supprimer $nom : des paiements ont déjà été enregistrés pour cet élève. Son historique financier doit être conservé.");
            $this->rediriger('/eleves/' . $id);
        }
        Eleve::supprimer($id);
        Session::message('succes', "L'élève $nom a été supprimé(e).");
        $this->rediriger('/eleves');
    }

    /**
     * Recherche d'un compte à partir de l'e-mail du parent (appel JavaScript du formulaire) :
     * indique si le compte existe déjà ou s'il sera créé.
     */
    public function rechercherParent(): void
    {
        exigerRole('admin');
        $email = mb_strtolower($this->query('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['valide' => false]);
        }
        $compte = User::trouverParEmail($email);
        if ($compte === null) {
            $this->json(['valide' => true, 'existe' => false]);
        }
        $this->json([
            'valide'    => true,
            'existe'    => true,
            'nom'       => $compte['nom'],
            'telephone' => $compte['telephone'],
            'role'      => $compte['role'],
            'actif'     => $compte['statut'] === 'actif',
            'enfants'   => count(Eleve::parParent((int) $compte['id_utilisateur'])),
        ]);
    }

    // --- Outils internes -------------------------------------------------------------

    private function donneesFormulaire(): array
    {
        $espaces = static fn(string $v): string => (string) preg_replace('/\s+/', ' ', $v);
        return [
            'matricule'        => mb_strtoupper($this->post('matricule')),
            'nom'              => $espaces($this->post('nom')),
            'prenom'           => $espaces($this->post('prenom')),
            'date_inscription' => $this->post('date_inscription'),
            'id_classe'        => (int) $this->post('id_classe', '0'),
            'email_parent'     => mb_strtolower($this->post('email_parent')),
            'nom_parent'       => $espaces($this->post('nom_parent')),
            'telephone_parent' => $this->post('telephone_parent'),
            'adresse_parent'   => $espaces($this->post('adresse_parent')),
        ];
    }

    /**
     * Valide l'élève et détermine le parent : compte existant (rôle parent, actif)
     * ou nouveau compte à créer (nom obligatoire).
     * @return array{0: array<string,string>, 1: ?array} erreurs, compte parent existant
     */
    private function validerFormulaire(array $donnees, ?int $idEleve): array
    {
        $nomPersonne = '/^[\p{L}][\p{L}\s\'’.-]*$/u';
        $erreurs = $this->valider($donnees, [
            'matricule'        => 'requis|min:3|max:50',
            'nom'              => 'requis|min:2|max:50',
            'prenom'           => 'requis|min:2|max:50',
            'date_inscription' => 'requis|date',
            'email_parent'     => 'requis|email|max:100',
        ]);

        if (!isset($erreurs['matricule'])) {
            if (!preg_match(Eleve::FORMAT_MATRICULE, $donnees['matricule'])) {
                $erreurs['matricule'] = 'Lettres, chiffres et tirets uniquement (ex. IO-2026-025).';
            } elseif (Eleve::matriculeExiste($donnees['matricule'], $idEleve)) {
                $erreurs['matricule'] = 'Ce matricule est déjà attribué à un autre élève.';
            }
        }
        foreach (['nom', 'prenom'] as $champ) {
            if (!isset($erreurs[$champ]) && !preg_match($nomPersonne, $donnees[$champ])) {
                $erreurs[$champ] = 'Lettres, espaces, apostrophes et tirets uniquement.';
            }
        }
        if (!isset($erreurs['date_inscription']) && $donnees['date_inscription'] > date('Y-m-d')) {
            $erreurs['date_inscription'] = 'La date d\'inscription ne peut pas être dans le futur.';
        }
        if (Classe::trouver($donnees['id_classe']) === null) {
            $erreurs['id_classe'] = 'Choisissez une classe.';
        }

        // Parent : compte existant ou création.
        $parent = null;
        if (!isset($erreurs['email_parent'])) {
            $parent = User::trouverParEmail($donnees['email_parent']);
            if ($parent !== null && $parent['role'] !== 'parent') {
                $erreurs['email_parent'] = 'Cette adresse appartient à un compte ' . mb_strtolower(libelleRole($parent['role'])) . ', pas à un parent.';
            } elseif ($parent !== null && $parent['statut'] !== 'actif') {
                $erreurs['email_parent'] = 'Le compte de ce parent est désactivé : réactivez-le d\'abord dans « Utilisateurs ».';
            } elseif ($parent === null) {
                $erreurs += $this->valider($donnees, [
                    'nom_parent'       => 'requis|min:2|max:100',
                    'telephone_parent' => 'telephone|max:20',
                    'adresse_parent'   => 'max:150',
                ]);
                if (!isset($erreurs['nom_parent']) && !preg_match($nomPersonne, $donnees['nom_parent'])) {
                    $erreurs['nom_parent'] = 'Lettres, espaces, apostrophes et tirets uniquement.';
                }
            }
        }
        return [$erreurs, $parent];
    }

    /**
     * Crée le compte parent si nécessaire, puis crée ou met à jour l'élève,
     * le tout dans une seule transaction. Le mot de passe provisoire du nouveau
     * parent est affiché une fois à l'administrateur et journalisé comme e-mail envoyé.
     * @return int identifiant de l'élève
     */
    private function enregistrerAvecParent(array $donnees, ?array $parent, ?int $idEleve): int
    {
        $db = Database::get();
        $motDePasse = null;
        $db->beginTransaction();
        try {
            if ($parent === null) {
                $motDePasse = genererMotDePasseProvisoire();
                $parent = [
                    'nom'       => $donnees['nom_parent'],
                    'email'     => $donnees['email_parent'],
                    'telephone' => $donnees['telephone_parent'],
                    'adresse'   => $donnees['adresse_parent'],
                ];
                $parent['id_utilisateur'] = User::creer($parent + ['role' => 'parent', 'mot_de_passe' => $motDePasse]);
            }
            $donnees['id_parent'] = (int) $parent['id_utilisateur'];

            if ($idEleve === null) {
                $idEleve = Eleve::creer($donnees);
            } else {
                Eleve::modifier($idEleve, $donnees);
            }
            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            // Doublon détecté par la base (inscription simultanée du même matricule ou e-mail).
            if ($e->getCode() === '23000') {
                $this->retourAvecErreurs(
                    $idEleve === null ? '/eleves/nouveau' : "/eleves/$idEleve/modifier",
                    ['matricule' => 'Ce matricule ou cet e-mail vient d\'être enregistré par ailleurs. Vérifiez la saisie.']
                );
            }
            throw $e;
        }

        if ($motDePasse !== null) {
            Notificateur::compteCree($parent, $motDePasse);
            Session::flash('mot_de_passe_provisoire', [
                'nom' => $parent['nom'], 'email' => $parent['email'], 'mot_de_passe' => $motDePasse,
            ]);
            Session::message('info', 'Un compte parent a été créé pour ' . $parent['nom'] . '.');
        }
        return $idEleve;
    }
}
