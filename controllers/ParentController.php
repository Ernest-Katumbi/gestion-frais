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

    /** Historique de tous les paiements (réussis, en attente, échoués) des enfants du parent. */
    public function historique(): void
    {
        $parent = exigerRole('parent');
        $enfants = Eleve::parParent((int) $parent['id_utilisateur']);
        $idEnfant = (int) $this->query('enfant', '0');
        // Filtre limité aux enfants du parent connecté.
        if (!in_array($idEnfant, array_map('intval', array_column($enfants, 'id_eleve')), true)) {
            $idEnfant = 0;
        }
        $paiements = array_values(array_filter(
            Paiement::parParent((int) $parent['id_utilisateur'], 500),
            static fn(array $p): bool => $idEnfant === 0 || (int) $p['id_eleve'] === $idEnfant
        ));
        $reussis = array_filter($paiements, static fn(array $p): bool => $p['statut'] === 'reussi');

        $this->vue('parent/historique', [
            'titre'     => 'Historique des paiements',
            'fil'       => [['Historique des paiements', null]],
            'paiements' => $paiements,
            'enfants'   => $enfants,
            'idEnfant'  => $idEnfant,
            'total'     => array_sum(array_map(static fn(array $p): float => (float) $p['montant'], $reussis)),
            'nbReussis' => count($reussis),
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

    // --- Paiement en ligne -----------------------------------------------------------

    /** Opérateurs Mobile Money proposés (sandbox). */
    public const OPERATEURS = ['mpesa' => 'M-Pesa', 'orange' => 'Orange Money', 'airtel' => 'Airtel Money'];

    /** Délai au-delà duquel un paiement en ligne sans réponse est considéré comme abandonné. */
    private const DELAI_EXPIRATION_MINUTES = 30;

    /** Formulaire de paiement : montant proposé = reste à payer, choix Mobile Money ou carte. */
    public function payer(int $id): void
    {
        $parent = exigerRole('parent');
        $frais = $this->fraisDuParent($id, $parent);
        if ($frais['statut'] === 'paye') {
            Session::message('info', 'Ce frais est déjà entièrement payé.');
            $this->rediriger('/parent/frais/' . $id);
        }
        Paiement::expirerEnAttente($id, self::DELAI_EXPIRATION_MINUTES);
        if ($enCours = Paiement::enAttentePourFrais($id)) {
            Session::message('info', 'Un paiement est déjà en cours pour ce frais : suivez sa confirmation ci-dessous.');
            $this->rediriger('/parent/paiements/' . (int) $enCours['id_paiement']);
        }

        $this->vue('parent/payer', [
            'titre'      => 'Payer en ligne',
            'fil'        => [[$frais['categorie'], '/parent/frais/' . $id], ['Paiement', null]],
            'f'          => $frais,
            'parent'     => $parent,
            'operateurs' => self::OPERATEURS,
            'taux'       => TauxChange::actuel(),
        ]);
    }

    /**
     * Crée le paiement « en attente » (référence unique) et demande à la passerelle
     * d'exécuter la transaction. Le statut ne changera que sur notification de la
     * passerelle (public/callback.php).
     */
    public function initierPaiement(int $id): void
    {
        $parent = exigerRole('parent');
        $frais = $this->fraisDuParent($id, $parent);
        $retour = "/parent/frais/$id/payer";

        if ($frais['statut'] === 'paye') {
            Session::message('info', 'Ce frais est déjà entièrement payé.');
            $this->rediriger('/parent/frais/' . $id);
        }
        Paiement::expirerEnAttente($id, self::DELAI_EXPIRATION_MINUTES);
        if ($enCours = Paiement::enAttentePourFrais($id)) {
            $this->rediriger('/parent/paiements/' . (int) $enCours['id_paiement']);
        }

        $taux = TauxChange::actuel();
        $devise = $this->post('devise', DEVISE);
        if (!in_array($devise, Monnaie::devisesAcceptees($taux), true)) {
            $devise = DEVISE;
        }
        $donnees = [
            'montant'   => str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $this->post('montant')),
            'mode'      => $this->post('mode'),
            'operateur' => $this->post('operateur'),
            'telephone' => $this->post('telephone'),
        ];
        $regles = ['montant' => 'requis|montant:' . $devise, 'mode' => 'requis|dans:mobile_money,carte'];
        if ($donnees['mode'] === 'mobile_money') {
            $regles += ['operateur' => 'requis|dans:' . implode(',', array_keys(self::OPERATEURS)), 'telephone' => 'requis|telephone'];
        }
        $erreurs = $this->valider($donnees, $regles);

        $versement = null;
        if (!isset($erreurs['montant'])) {
            // Montant proposé = reste à payer ; paiement partiel possible mais jamais au-delà.
            // Un versement en dollars est converti en francs au taux du jour, figé à l'initiation.
            $minimum = Frais::versementMinimal((float) $frais['reste']);
            try {
                $versement = Monnaie::convertir((float) $donnees['montant'], $devise, $taux ? (float) $taux['taux'] : null, (float) $frais['reste']);
                $montant = $versement['montant_base'];
                if ($montant < $minimum) {
                    throw new DomainException('Le montant minimal est de ' . formaterMontant($minimum) . '.');
                }
                Frais::statutApres((float) $frais['montant'], (float) $frais['montant_paye'], $montant);
            } catch (DomainException $e) {
                $erreurs['montant'] = $e->getMessage();
            }
        }
        if ($erreurs) {
            $this->retourAvecErreurs($retour, $erreurs);
        }

        $reference = Paiement::genererReference();
        $idPaiement = Paiement::creer([
            'reference'     => $reference,
            'montant'       => $montant,
            'devise_versee' => $versement['devise'],
            'montant_verse' => $versement['montant_verse'],
            'taux_applique' => $versement['taux'],
            'mode'          => $donnees['mode'],
            'statut'        => 'en_attente',
            'id_frais'      => $id,
        ]);

        try {
            // La passerelle débite le montant dans la devise choisie par le parent.
            $transaction = passerelle()->initierTransaction($versement['montant_verse'], $donnees['mode'], $reference, [
                'devise'     => $versement['devise'],
                'nom'        => $parent['nom'],
                'email'      => $parent['email'],
                'telephone'  => $donnees['mode'] === 'mobile_money' ? $donnees['telephone'] : (string) $parent['telephone'],
                'operateur'  => $donnees['mode'] === 'mobile_money' ? self::OPERATEURS[$donnees['operateur']] : null,
                'url_retour' => url('/parent/paiements/' . $idPaiement),
            ]);
        } catch (Throwable $e) {
            Paiement::changerStatut($idPaiement, 'echoue');
            journaliser('app', "Initiation refusée pour $reference : " . $e->getMessage());
            Session::message('erreur', 'Le service de paiement est momentanément indisponible. Aucun montant n\'a été débité ; réessayez plus tard ou payez au guichet.');
            $this->rediriger($retour);
        }

        Session::set('url_paiement_' . $idPaiement, $transaction['url_paiement']);
        // Carte : redirection vers la page hébergée par la passerelle (les données de carte
        // ne transitent jamais par l'application). Mobile Money : attente de la confirmation.
        if ($donnees['mode'] === 'carte') {
            rediriger($transaction['url_paiement']);
        }
        $this->rediriger('/parent/paiements/' . $idPaiement);
    }

    /** Suivi d'un paiement en ligne : en attente (actualisé en direct), réussi ou échoué. */
    public function suivi(int $id): void
    {
        $parent = exigerRole('parent');
        $paiement = $this->paiementDuParent($id, $parent);
        $raison = null;
        if ($paiement['statut'] === 'echoue') {
            $notification = Notification::pourReference((int) $parent['id_utilisateur'], $paiement['reference']);
            $raison = $notification && preg_match('/\)\s*:\s*(.+?)\. Aucun montant/u', $notification['message'], $m) ? $m[1] : null;
        }
        $this->vue('parent/suivi', [
            'titre'       => 'Suivi du paiement',
            'fil'         => [[$paiement['categorie'], '/parent/frais/' . (int) $paiement['id_frais']], ['Suivi du paiement', null]],
            'p'           => $paiement,
            'raison'      => $raison,
            'urlPaiement' => Session::get('url_paiement_' . $id) ?? url('/simulateur/', ['ref' => $paiement['reference']]),
        ]);
    }

    /** Statut du paiement en JSON (interrogé toutes les 3 secondes par la page de suivi). */
    public function statut(int $id): void
    {
        $parent = exigerRole('parent');
        $paiement = $this->paiementDuParent($id, $parent);
        $this->json([
            'statut'  => $paiement['statut'],
            'libelle' => Paiement::STATUTS[$paiement['statut']],
        ]);
    }

    /**
     * Charge un paiement en vérifiant qu'il concerne un enfant du parent connecté.
     */
    private function paiementDuParent(int $idPaiement, array $parent): array
    {
        $paiement = Paiement::trouver($idPaiement) ?? abort(404, 'Ce paiement n\'existe pas.');
        if ((int) $paiement['id_parent'] !== (int) $parent['id_utilisateur']) {
            abort(403, 'Ce paiement ne concerne pas l\'un de vos enfants.');
        }
        return $paiement;
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
