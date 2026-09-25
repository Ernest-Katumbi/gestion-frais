<?php
declare(strict_types=1);

/**
 * Gestion des classes (administrateur).
 */
final class ClasseController extends Controller
{
    public function index(): void
    {
        exigerRole('admin');
        $recherche = $this->query('q');
        $this->vue('admin/classes', [
            'titre'     => 'Classes',
            'fil'       => [['Classes', null]],
            'classes'   => Classe::toutes($recherche),
            'recherche' => $recherche,
        ]);
    }

    public function creer(): void
    {
        exigerRole('admin');
        $this->vue('admin/classe_form', [
            'titre'  => 'Nouvelle classe',
            'fil'    => [['Classes', '/classes'], ['Nouvelle classe', null]],
            'classe' => null,
        ]);
    }

    public function enregistrer(): void
    {
        exigerRole('admin');
        $donnees = $this->donneesFormulaire();
        $erreurs = $this->validerFormulaire($donnees, null);
        if ($erreurs) {
            $this->retourAvecErreurs('/classes/nouveau', $erreurs);
        }
        Classe::creer($donnees);
        Session::message('succes', 'La classe « ' . $donnees['libelle'] . ' » a été créée.');
        $this->rediriger('/classes');
    }

    public function modifier(int $id): void
    {
        exigerRole('admin');
        $classe = Classe::trouver($id) ?? abort(404, 'Cette classe n\'existe pas.');
        $this->vue('admin/classe_form', [
            'titre'  => 'Modifier une classe',
            'fil'    => [['Classes', '/classes'], [$classe['libelle'], null]],
            'classe' => $classe,
        ]);
    }

    public function mettreAJour(int $id): void
    {
        exigerRole('admin');
        $classe = Classe::trouver($id) ?? abort(404, 'Cette classe n\'existe pas.');
        $donnees = $this->donneesFormulaire();
        $erreurs = $this->validerFormulaire($donnees, (int) $classe['id_classe']);
        if ($erreurs) {
            $this->retourAvecErreurs("/classes/$id/modifier", $erreurs);
        }
        Classe::modifier($id, $donnees);
        Session::message('succes', 'La classe « ' . $donnees['libelle'] . ' » a été mise à jour.');
        $this->rediriger('/classes');
    }

    /** Suppression protégée : une classe comptant des élèves ne peut pas être supprimée. */
    public function supprimer(int $id): void
    {
        exigerRole('admin');
        $classe = Classe::trouver($id) ?? abort(404, 'Cette classe n\'existe pas.');
        $nombre = Classe::compterEleves($id);
        if ($nombre > 0) {
            Session::message('erreur', sprintf(
                'Impossible de supprimer « %s » : %d élève%s y %s inscrit%s. Changez-les de classe au préalable.',
                $classe['libelle'], $nombre, $nombre > 1 ? 's' : '', $nombre > 1 ? 'sont' : 'est', $nombre > 1 ? 's' : ''
            ));
            $this->rediriger('/classes');
        }
        Classe::supprimer($id);
        Session::message('succes', 'La classe « ' . $classe['libelle'] . ' » a été supprimée.');
        $this->rediriger('/classes');
    }

    // --- Outils internes -------------------------------------------------------------

    private function donneesFormulaire(): array
    {
        $niveau = $this->post('niveau');
        $section = preg_replace('/\s+/', ' ', $this->post('section'));
        $libelle = preg_replace('/\s+/', ' ', $this->post('libelle'));
        // Libellé par défaut : « 4e Commerciale ».
        if ($libelle === '' && $niveau !== '' && $section !== '') {
            $libelle = $niveau . ' ' . $section;
        }
        return ['niveau' => $niveau, 'section' => $section, 'libelle' => $libelle];
    }

    private function validerFormulaire(array $donnees, ?int $idExistant): array
    {
        $erreurs = $this->valider($donnees, [
            'niveau'  => 'requis|dans:' . implode(',', Classe::NIVEAUX),
            'section' => 'requis|min:2|max:50',
            'libelle' => 'requis|min:2|max:50',
        ]);
        if (!isset($erreurs['libelle']) && Classe::libelleExiste($donnees['libelle'], $idExistant)) {
            $erreurs['libelle'] = 'Une classe porte déjà ce libellé.';
        }
        return $erreurs;
    }
}
