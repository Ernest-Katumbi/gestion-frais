<?php
declare(strict_types=1);

/**
 * Espace parent : enfants, frais dus, détail d'un frais et reçus.
 * Un parent ne voit que ses propres enfants (contrôle sur eleve.id_parent).
 */
final class ParentController extends Controller
{
    public function accueil(): void
    {
        $parent = exigerRole('parent');
        $enfants = [];
        $tousLesFrais = [];
        foreach (Eleve::parParent((int) $parent['id_utilisateur']) as $enfant) {
            $frais = Frais::parEleve((int) $enfant['id_eleve']);
            $tousLesFrais = array_merge($tousLesFrais, $frais);
            $enfants[] = $enfant + ['frais' => $frais, 'totaux' => Frais::totaux($frais)];
        }

        $this->vue('parent/accueil', [
            'titre'       => 'Accueil',
            'utilisateur' => $parent,
            'enfants'     => $enfants,
            'totaux'      => Frais::totaux($tousLesFrais),
            'enRetard'    => count(array_filter($tousLesFrais, static fn(array $f): bool => $f['statut'] !== 'paye' && $f['echeance'] < date('Y-m-d'))),
            'derniers'    => array_slice(array_filter(
                Paiement::parParent((int) $parent['id_utilisateur'], 10),
                static fn(array $p): bool => $p['statut'] === 'reussi'
            ), 0, 3),
        ]);
    }

    /** Détail d'un frais : situation, historique des paiements et reçus. */
    public function frais(int $id): void
    {
        $parent = exigerRole('parent');
        $frais = $this->fraisDuParent($id, $parent);

        $this->vue('parent/frais', [
            'titre'     => $frais['categorie'],
            'fil'       => [[$frais['eleve_prenom'] . ' ' . $frais['eleve_nom'], null], [$frais['categorie'], null]],
            'f'         => $frais,
            'paiements' => Paiement::parFrais($id),
        ]);
    }

    /**
     * Charge un frais en vérifiant qu'il concerne bien un enfant du parent connecté.
     * Réponse 403 si le frais appartient à un autre parent.
     */
    private function fraisDuParent(int $idFrais, array $parent): array
    {
        $frais = Frais::trouver($idFrais) ?? abort(404, 'Ce frais n\'existe pas.');
        if ((int) $frais['id_parent'] !== (int) $parent['id_utilisateur']) {
            abort(403, 'Ce frais ne concerne pas l\'un de vos enfants.');
        }
        return $frais;
    }
}
