<?php
declare(strict_types=1);

/**
 * Gestion financière du comptable : catégories de frais, affectation des frais
 * aux classes et suivi des impayés.
 */
final class FraisController extends Controller
{
    // --- Catégories de frais ----------------------------------------------------------

    public function categories(): void
    {
        exigerRole('comptable');
        $this->vue('comptable/categories', [
            'titre'      => 'Catégories de frais',
            'fil'        => [['Catégories de frais', null]],
            'categories' => CategorieFrais::toutes(),
        ]);
    }

    public function categorieCreer(): void
    {
        exigerRole('comptable');
        $this->vue('comptable/categorie_form', [
            'titre'     => 'Nouvelle catégorie',
            'fil'       => [['Catégories de frais', '/categories'], ['Nouvelle catégorie', null]],
            'categorie' => null,
        ]);
    }

    public function categorieEnregistrer(): void
    {
        exigerRole('comptable');
        $donnees = $this->donneesCategorie();
        $erreurs = $this->validerCategorie($donnees, null);
        if ($erreurs) {
            $this->retourAvecErreurs('/categories/nouveau', $erreurs);
        }
        CategorieFrais::creer($donnees);
        Session::message('succes', 'La catégorie « ' . $donnees['libelle'] . ' » a été créée.');
        $this->rediriger('/categories');
    }

    public function categorieModifier(int $id): void
    {
        exigerRole('comptable');
        $categorie = CategorieFrais::trouver($id) ?? abort(404, 'Cette catégorie n\'existe pas.');
        $this->vue('comptable/categorie_form', [
            'titre'     => 'Modifier une catégorie',
            'fil'       => [['Catégories de frais', '/categories'], [$categorie['libelle'], null]],
            'categorie' => $categorie,
            'utilisee'  => CategorieFrais::estUtilisee($id),
        ]);
    }

    public function categorieMettreAJour(int $id): void
    {
        exigerRole('comptable');
        CategorieFrais::trouver($id) ?? abort(404, 'Cette catégorie n\'existe pas.');
        $donnees = $this->donneesCategorie();
        $erreurs = $this->validerCategorie($donnees, $id);
        if ($erreurs) {
            $this->retourAvecErreurs("/categories/$id/modifier", $erreurs);
        }
        CategorieFrais::modifier($id, $donnees);
        Session::message('succes', 'La catégorie « ' . $donnees['libelle'] . ' » a été mise à jour.');
        $this->rediriger('/categories');
    }

    /** Suppression protégée : refusée si des frais utilisent la catégorie. */
    public function categorieSupprimer(int $id): void
    {
        exigerRole('comptable');
        $categorie = CategorieFrais::trouver($id) ?? abort(404, 'Cette catégorie n\'existe pas.');
        if (CategorieFrais::estUtilisee($id)) {
            Session::message('erreur', 'Impossible de supprimer « ' . $categorie['libelle'] . ' » : des frais ont déjà été affectés avec cette catégorie.');
            $this->rediriger('/categories');
        }
        CategorieFrais::supprimer($id);
        Session::message('succes', 'La catégorie « ' . $categorie['libelle'] . ' » a été supprimée.');
        $this->rediriger('/categories');
    }

    // --- Affectation des frais ------------------------------------------------------------

    public function index(): void
    {
        exigerRole('comptable');
        $this->vue('comptable/frais', [
            'titre'        => 'Affectation des frais',
            'fil'          => [['Affectation des frais', null]],
            'affectations' => Frais::affectations(),
            'categories'   => CategorieFrais::toutes(),
            'classes'      => Classe::toutes(),
        ]);
    }

    /** Traitement du formulaire : une catégorie, une échéance, une ou plusieurs classes. */
    public function affecter(): void
    {
        exigerRole('comptable');
        $idCategorie = (int) $this->post('id_categorie', '0');
        $echeance = $this->post('echeance');
        $idsClasses = array_values(array_unique(array_map('intval', (array) ($_POST['classes'] ?? []))));

        $erreurs = $this->valider(['echeance' => $echeance], ['echeance' => 'requis|date']);
        $categorie = CategorieFrais::trouver($idCategorie);
        if ($categorie === null) {
            $erreurs['id_categorie'] = 'Choisissez une catégorie.';
        }
        $classes = array_filter(array_map([Classe::class, 'trouver'], $idsClasses));
        if ($classes === [] || count($classes) !== count($idsClasses)) {
            $erreurs['classes'] = 'Choisissez au moins une classe.';
        }
        if ($erreurs) {
            $this->retourAvecErreurs('/frais', $erreurs);
        }

        $crees = 0;
        $concernes = 0;
        foreach ($classes as $classe) {
            $crees += $this->assigner($idCategorie, (int) $classe['id_classe'], $echeance);
            $concernes += (int) $classe['nb_eleves'];
        }
        $ignores = $concernes - $crees;

        if ($crees === 0) {
            Session::message('avertissement', $concernes === 0
                ? 'Aucun élève dans la ou les classes choisies : aucun frais créé.'
                : 'Ces frais étaient déjà affectés à tous les élèves concernés : aucun doublon n\'a été créé.');
        } else {
            Session::message('succes', sprintf(
                '« %s » (échéance %s) affecté : %d frais créé%s de %s chacun%s.',
                $categorie['libelle'],
                formaterDate($echeance),
                $crees,
                $crees > 1 ? 's' : '',
                formaterMontant($categorie['montant_defaut']),
                $ignores > 0 ? " ; $ignores élève" . ($ignores > 1 ? 's' : '') . ' déjà concerné' . ($ignores > 1 ? 's' : '') . ' ignoré' . ($ignores > 1 ? 's' : '') : ''
            ));
        }
        $this->rediriger('/frais');
    }

    /**
     * Affecte une catégorie de frais à tous les élèves d'une classe pour une échéance.
     * Crée un frais « impayé » (montant par défaut de la catégorie) par élève, dans
     * une transaction ; les doublons (même élève, catégorie et échéance) sont ignorés
     * sans erreur. Renvoie le nombre de frais créés.
     */
    public function assigner(int $idCategorie, int $idClasse, string $echeance): int
    {
        $db = Database::get();
        $db->beginTransaction();
        try {
            $requete = $db->prepare(
                "INSERT INTO frais (montant, montant_paye, echeance, statut, id_eleve, id_categorie)
                 SELECT c.montant_defaut, 0, ?, 'impaye', e.id_eleve, c.id_categorie
                 FROM eleve e
                 JOIN categorie_frais c ON c.id_categorie = ?
                 WHERE e.id_classe = ?
                   AND NOT EXISTS (
                       SELECT 1 FROM frais f
                       WHERE f.id_eleve = e.id_eleve AND f.id_categorie = c.id_categorie AND f.echeance = ?
                   )"
            );
            $requete->execute([$echeance, $idCategorie, $idClasse, $echeance]);
            $crees = $requete->rowCount();
            $db->commit();
            return $crees;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /** Annule une affectation erronée tant qu'aucun paiement n'a été enregistré. */
    public function annuler(): void
    {
        exigerRole('comptable');
        $idCategorie = (int) $this->post('id_categorie', '0');
        $idClasse = (int) $this->post('id_classe', '0');
        $echeance = $this->post('echeance');
        if (!dateValide($echeance)) {
            abort(404);
        }
        try {
            $n = Frais::annulerAffectation($idCategorie, $idClasse, $echeance);
            Session::message('succes', "Affectation annulée : $n frais supprimé" . ($n > 1 ? 's' : '') . '.');
        } catch (DomainException $e) {
            Session::message('erreur', $e->getMessage());
        }
        $this->rediriger('/frais');
    }

    // --- Impayés ---------------------------------------------------------------------

    public function impayes(): void
    {
        exigerRole('comptable');
        $filtres = $this->filtresImpayes();
        $page = $this->page();
        $resultat = Frais::impayes($filtres, $page, 20);

        $this->vue('comptable/impayes', [
            'titre'      => 'Impayés',
            'fil'        => [['Impayés', null]],
            'impayes'    => $resultat['lignes'],
            'totaux'     => $resultat['totaux'],
            'filtres'    => $filtres,
            'classes'    => Classe::options(),
            'categories' => CategorieFrais::options(),
            'pagination' => ['page' => $page, 'parPage' => 20, 'total' => $resultat['total']],
        ]);
    }

    /** Export PDF de la liste des impayés, avec les mêmes filtres que l'écran. */
    public function impayesPdf(): void
    {
        $comptable = exigerRole('comptable');
        $filtres = $this->filtresImpayes();
        $lignes = Frais::tousImpayes($filtres);
        $criteres = array_filter([
            $filtres['classe'] ? 'classe ' . (Classe::trouver($filtres['classe'])['libelle'] ?? '?') : null,
            $filtres['categorie'] ? (CategorieFrais::trouver($filtres['categorie'])['libelle'] ?? '?') : null,
            $filtres['echeance_max'] !== '' ? 'échéance jusqu\'au ' . formaterDate($filtres['echeance_max']) : null,
            $filtres['echus'] ? 'échéance dépassée' : null,
        ]);
        $html = View::rendre('pdf/impayes', [
            'lignes'    => $lignes,
            'totaux'    => Frais::totaux($lignes),
            'criteres'  => $criteres,
            'comptable' => $comptable,
            'logo'      => 'data:image/png;base64,' . base64_encode((string) file_get_contents(RACINE . '/public/assets/img/logo-institut.png')),
        ], null);
        $pdf = GenerateurRecu::rendrePdf($html, 'A4', 'landscape');
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="impayes-' . date('Y-m-d') . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store');
        echo $pdf;
        exit;
    }

    // --- Outils internes -------------------------------------------------------------

    /** Filtres de la liste des impayés (classe, catégorie, échéance maximale, échus). */
    private function filtresImpayes(): array
    {
        return [
            'classe'       => (int) $this->query('classe', '0'),
            'categorie'    => (int) $this->query('categorie', '0'),
            'echeance_max' => dateValide($this->query('echeance_max')) ? $this->query('echeance_max') : '',
            'echus'        => $this->query('echus') === '1',
        ];
    }

    private function donneesCategorie(): array
    {
        return [
            'code'           => mb_strtoupper($this->post('code')),
            'libelle'        => (string) preg_replace('/\s+/', ' ', $this->post('libelle')),
            'montant_defaut' => str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $this->post('montant_defaut')),
            'periodicite'    => $this->post('periodicite'),
        ];
    }

    private function validerCategorie(array $donnees, ?int $idExistant): array
    {
        $erreurs = $this->valider($donnees, [
            'code'           => 'requis|min:2|max:20',
            'libelle'        => 'requis|min:3|max:100',
            'montant_defaut' => 'requis|montant',
            'periodicite'    => 'requis|dans:' . implode(',', array_keys(CategorieFrais::PERIODICITES)),
        ]);
        if (!isset($erreurs['code']) && !preg_match('/^[A-Z0-9_-]+$/', $donnees['code'])) {
            $erreurs['code'] = 'Lettres majuscules, chiffres, tirets et soulignés uniquement (ex. MIN-T1).';
        } elseif (!isset($erreurs['code']) && CategorieFrais::codeExiste($donnees['code'], $idExistant)) {
            $erreurs['code'] = 'Ce code est déjà utilisé par une autre catégorie.';
        }
        if (!isset($erreurs['montant_defaut']) && (float) $donnees['montant_defaut'] > 99999999.99) {
            $erreurs['montant_defaut'] = 'Montant trop élevé.';
        }
        return $erreurs;
    }
}
