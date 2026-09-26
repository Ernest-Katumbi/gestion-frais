<?php
declare(strict_types=1);

/**
 * Règles métier des frais : statut (impayé / partiel / payé), versement nul,
 * dépassement du reste, mise à jour atomique et affectation sans doublon.
 */
final class FraisTest extends BaseDeTest
{
    // --- Frais::statutApres (fonction pure) --------------------------------------------

    public function testUnFraisNonPayeEstImpaye(): void
    {
        $frais = $this->frais();
        $this->assertSame('impaye', $frais['statut']);
        $this->assertSame('0.00', $frais['montant_paye']);
    }

    public function testUnVersementPartielDonneLeStatutPartiel(): void
    {
        $this->assertSame('partiel', Frais::statutApres(150.00, 0.00, 60.00));
        $this->assertSame('partiel', Frais::statutApres(150.00, 60.00, 89.99));
    }

    public function testLeVersementDuResteDonneLeStatutPaye(): void
    {
        $this->assertSame('paye', Frais::statutApres(150.00, 0.00, 150.00));
        $this->assertSame('paye', Frais::statutApres(150.00, 60.00, 90.00));
    }

    public function testLesCentimesNeProvoquentPasDErreurDArrondi(): void
    {
        // 0,10 + 0,20 vaut 0,30000000000000004 en virgule flottante : le calcul en centimes l'évite.
        $this->assertSame('paye', Frais::statutApres(0.30, 0.10, 0.20));
    }

    public function testUnVersementNulEstRefuse(): void
    {
        $this->expectException(DomainException::class);
        Frais::statutApres(150.00, 0.00, 0.00);
    }

    public function testUnVersementNegatifEstRefuse(): void
    {
        $this->expectException(DomainException::class);
        Frais::statutApres(150.00, 0.00, -10.00);
    }

    public function testUnVersementSuperieurAuResteEstRefuse(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/dépasse le reste à payer/');
        Frais::statutApres(150.00, 100.00, 50.01);
    }

    // --- Frais::enregistrerVersement (base de test) -----------------------------------

    public function testLeVersementMetAJourMontantEtStatutEnUneFois(): void
    {
        Frais::enregistrerVersement($this->ids['frais'], 60.00);
        $this->assertSame(['60.00', 'partiel'], [$this->frais()['montant_paye'], $this->frais()['statut']]);

        Frais::enregistrerVersement($this->ids['frais'], 90.00);
        $this->assertSame(['150.00', 'paye'], [$this->frais()['montant_paye'], $this->frais()['statut']]);
    }

    public function testUnVersementQuiDepasseLeResteNeModifieRien(): void
    {
        Frais::enregistrerVersement($this->ids['frais'], 100.00);
        try {
            Frais::enregistrerVersement($this->ids['frais'], 50.01);
            $this->fail('Le dépassement aurait dû être refusé.');
        } catch (DomainException) {
            // attendu : aucune ligne modifiée par l'UPDATE conditionnel
        }
        $this->assertSame(['100.00', 'partiel'], [$this->frais()['montant_paye'], $this->frais()['statut']]);
    }

    public function testUnVersementSurUnFraisInexistantEstRefuse(): void
    {
        $this->expectException(DomainException::class);
        Frais::enregistrerVersement(999999, 10.00);
    }

    // --- FraisController::assigner -------------------------------------------------------

    public function testLAffectationIgnoreLesDoublonsEtRenvoieLeNombreCree(): void
    {
        Eleve::creer([
            'matricule' => 'IO-2026-002', 'nom' => 'Kabongo', 'prenom' => 'Glody', 'date_inscription' => date('Y-m-d'),
            'id_classe' => $this->ids['classe'], 'id_parent' => $this->ids['parent'],
        ]);
        $echeance = (string) $this->valeur('SELECT echeance FROM frais LIMIT 1');
        $affectation = new FraisController();

        // Le premier élève a déjà ce frais : seul le nouvel élève est concerné.
        $this->assertSame(1, $affectation->assigner($this->ids['categorie'], $this->ids['classe'], $echeance));
        $this->assertSame(0, $affectation->assigner($this->ids['categorie'], $this->ids['classe'], $echeance));
        $this->assertSame(2, (int) $this->valeur('SELECT COUNT(*) FROM frais'));
        $this->assertSame(0, (int) $this->valeur("SELECT COUNT(*) FROM frais WHERE statut <> 'impaye' OR montant <> 150"));
    }
}
