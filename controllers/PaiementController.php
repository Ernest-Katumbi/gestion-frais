<?php
declare(strict_types=1);

/**
 * Paiements : encaissement au guichet (comptable), liste, détail,
 * et téléchargement des reçus (avec contrôle de propriété pour les parents).
 */
final class PaiementController extends Controller
{
    private const PAR_PAGE = 20;

    // --- Encaissement au guichet ---------------------------------------------------------

    /**
     * Écran du guichet en deux temps : recherche de l'élève, puis choix du frais
     * et saisie du montant versé.
     */
    public function guichet(): void
    {
        exigerRole('comptable');
        $recherche = $this->query('q');
        $idFrais = (int) $this->query('frais', '0');
        $idEleve = (int) $this->query('eleve', '0');

        // Accès direct depuis la liste des impayés : ?frais=ID.
        if ($idFrais > 0 && $idEleve === 0) {
            $frais = Frais::trouver($idFrais) ?? abort(404, 'Ce frais n\'existe pas.');
            $idEleve = (int) $frais['id_eleve'];
        }

        $eleve = $idEleve > 0 ? (Eleve::trouver($idEleve) ?? abort(404, 'Cet élève n\'existe pas.')) : null;
        $fraisEleve = $eleve ? Frais::parEleve($idEleve) : [];
        $nonSoldes = array_values(array_filter($fraisEleve, static fn(array $f): bool => $f['statut'] !== 'paye'));

        $this->vue('comptable/guichet', [
            'titre'      => 'Encaisser au guichet',
            'fil'        => [['Encaisser au guichet', null]],
            'recherche'  => $recherche,
            'resultats'  => $recherche !== '' && $eleve === null ? Eleve::lister($recherche, 0, 1, 8)['lignes'] : [],
            'eleve'      => $eleve,
            'nonSoldes'  => $nonSoldes,
            'totaux'     => Frais::totaux($fraisEleve),
            'nbSoldes'   => count($fraisEleve) - count($nonSoldes),
            'idFraisChoisi' => $idFrais,
        ]);
    }

    /**
     * Enregistre un paiement en espèces : paiement « reussi » immédiat, comptable renseigné,
     * mise à jour du frais, reçu et notification du parent, le tout dans une transaction.
     */
    public function encaisser(): void
    {
        $comptable = exigerRole('comptable');
        $idFrais = (int) $this->post('id_frais', '0');
        $idEleve = (int) $this->post('id_eleve', '0');
        $retour = "/paiements/guichet?eleve=$idEleve&frais=$idFrais";

        $saisie = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $this->post('montant'));
        $erreurs = $this->valider(['montant' => $saisie], ['montant' => 'requis|montant']);
        if ($idFrais <= 0) {
            $erreurs['id_frais'] = 'Choisissez le frais à régler.';
        }
        if ($erreurs) {
            $this->retourAvecErreurs($retour, $erreurs);
        }
        $montant = round((float) $saisie, 2);

        $db = Database::get();
        $db->beginTransaction();
        try {
            // Verrou sur le frais : deux encaissements simultanés ne peuvent pas dépasser le montant dû.
            $frais = Frais::verrouiller($idFrais) ?? throw new DomainException('Ce frais n\'existe pas.');
            if ($frais['statut'] === 'paye') {
                throw new DomainException('Ce frais est déjà entièrement payé.');
            }
            $minimum = Frais::versementMinimal((float) $frais['reste']);
            if ($montant < $minimum) {
                throw new DomainException('Le montant minimal d\'un versement est de ' . formaterMontant($minimum) . '.');
            }
            // Règle métier : lève une DomainException si le versement dépasse le reste à payer.
            $nouveauStatut = Frais::statutApres((float) $frais['montant'], (float) $frais['montant_paye'], $montant);

            $idPaiement = Paiement::creer([
                'reference'    => Paiement::genererReference(),
                'montant'      => $montant,
                'mode'         => 'especes',
                'statut'       => 'reussi',
                'id_frais'     => $idFrais,
                'id_comptable' => (int) $comptable['id_utilisateur'],
            ]);
            Frais::enregistrerVersement($idFrais, $montant);
            GenerateurRecu::generer($idPaiement);
            Notificateur::paiementConfirme($idPaiement);
            $db->commit();
        } catch (DomainException $e) {
            $db->rollBack();
            $this->retourAvecErreurs($retour, ['montant' => $e->getMessage()], $e->getMessage());
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        Session::message('succes', sprintf(
            'Paiement de %s enregistré pour %s %s. %s',
            formaterMontant($montant),
            $frais['eleve_prenom'],
            $frais['eleve_nom'],
            $nouveauStatut === 'paye' ? 'Le frais est soldé.' : 'Le frais reste partiellement payé.'
        ));
        $this->rediriger('/paiements/' . $idPaiement);
    }

    // --- Consultation -------------------------------------------------------------------

    public function index(): void
    {
        exigerRole('comptable');
        $filtres = [
            'q'      => $this->query('q'),
            'statut' => $this->query('statut'),
            'mode'   => $this->query('mode'),
            'du'     => dateValide($this->query('du')) ? $this->query('du') : '',
            'au'     => dateValide($this->query('au')) ? $this->query('au') : '',
        ];
        $page = $this->page();
        $resultat = Paiement::lister($filtres, $page, self::PAR_PAGE);

        $this->vue('comptable/paiements', [
            'titre'      => 'Paiements',
            'fil'        => [['Paiements', null]],
            'paiements'  => $resultat['lignes'],
            'somme'      => $resultat['somme_reussie'],
            'filtres'    => $filtres,
            'pagination' => ['page' => $page, 'parPage' => self::PAR_PAGE, 'total' => $resultat['total']],
        ]);
    }

    public function afficher(int $id): void
    {
        exigerRole('comptable');
        $paiement = Paiement::trouver($id) ?? abort(404, 'Ce paiement n\'existe pas.');
        $this->vue('comptable/paiement', [
            'titre'    => 'Paiement ' . $paiement['reference'],
            'fil'      => [['Paiements', '/paiements'], [$paiement['reference'], null]],
            'p'        => $paiement,
            'qr'       => $paiement['code_qr'] ? GenerateurRecu::qrCode($paiement['code_qr'], 4) : null,
            'autres'   => array_filter(Paiement::parFrais((int) $paiement['id_frais']), static fn(array $x): bool => (int) $x['id_paiement'] !== $id),
        ]);
    }

    /**
     * Reçu PDF. Le comptable et l'administrateur voient tous les reçus ;
     * un parent uniquement ceux de ses enfants (contrôle de propriété sur id_parent).
     */
    public function recu(int $id): void
    {
        $utilisateur = exigerRole('comptable', 'admin', 'parent');
        $recu = Recu::trouver($id) ?? abort(404, 'Ce reçu n\'existe pas.');
        $paiement = Paiement::trouver((int) $recu['id_paiement']);
        if ($utilisateur['role'] === 'parent' && (int) $paiement['id_parent'] !== (int) $utilisateur['id_utilisateur']) {
            abort(403, 'Ce reçu ne concerne pas l\'un de vos enfants.');
        }

        $chemin = GenerateurRecu::cheminPdf($paiement);
        $disposition = $this->query('telecharger') === '1' ? 'attachment' : 'inline';
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposition . '; filename="' . $recu['numero'] . '.pdf"');
        header('Content-Length: ' . filesize($chemin));
        header('Cache-Control: private, no-store');
        readfile($chemin);
        exit;
    }
}
