<?php
declare(strict_types=1);

/**
 * Règles monétaires : devise de base (franc), conversion des versements en dollars au taux
 * du jour, tolérance d'arrondi, historique des taux et formatage des montants.
 */
final class MonnaieTest extends BaseDeTest
{
    public function testLaDeviseDeBaseParDefautEstLeFranc(): void
    {
        $this->assertSame('CDF', DEVISE);
        $this->assertSame('FC', symboleDevise());
        $this->assertSame(0, decimalesDevise());
    }

    public function testLesMontantsSontFormatesSelonLaDevise(): void
    {
        $this->assertSame("450\u{00A0}000\u{00A0}FC", formaterMontant(450000));
        $this->assertSame("150,00\u{00A0}USD", formaterMontant(150, 'USD'));
        $this->assertSame("1\u{00A0}USD = 2\u{00A0}850\u{00A0}FC", formaterTaux(2850));
    }

    public function testUnVersementEnFrancsNestPasConverti(): void
    {
        $v = Monnaie::convertir(150000, DEVISE, 2850, 450000);
        $this->assertSame([150000.0, 'CDF', null, false], [$v['montant_base'], $v['devise'], $v['taux'], $v['arrondi']]);
    }

    public function testUnVersementEnDollarsEstConvertiAuTauxDuJour(): void
    {
        $v = Monnaie::convertir(150.00, 'USD', 2850, 450000);
        $this->assertSame(427500.0, $v['montant_base']);
        $this->assertSame(150.0, $v['montant_verse']);
        $this->assertSame(2850.0, $v['taux']);
        $this->assertFalse($v['arrondi']);
    }

    public function testLaToleranceDArrondiSoldeExactementLeReste(): void
    {
        // 150 000 FC / 2 850 = 52,63… USD : le client verse 52,64 USD = 150 024 FC.
        $this->assertSame(52.64, Monnaie::versDevise(150000, 2850));
        $v = Monnaie::convertir(52.64, 'USD', 2850, 150000);
        $this->assertSame(150000.0, $v['montant_base']);
        $this->assertTrue($v['arrondi']);
    }

    public function testAuDelaDUnCentimeLeDepassementNestPasAbsorbe(): void
    {
        // 52,66 USD = 150 081 FC : dépasse le reste de plus d'un centime de dollar.
        $v = Monnaie::convertir(52.66, 'USD', 2850, 150000);
        $this->assertSame(150081.0, $v['montant_base']);
        $this->expectException(DomainException::class);
        Frais::statutApres(150000, 0, $v['montant_base']);
    }

    public function testSansTauxLePaiementEnDollarsEstRefuse(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/Aucun taux de change/');
        Monnaie::convertir(10, 'USD', null, 450000);
    }

    public function testUneDeviseNonAccepteeEstRefusee(): void
    {
        $this->expectException(DomainException::class);
        Monnaie::convertir(10, 'EUR', 3000, 450000);
    }

    public function testLeDollarNestProposeQuUneFoisUnTauxSaisi(): void
    {
        $this->assertSame(['CDF'], Monnaie::devisesAcceptees(TauxChange::actuel()));
        TauxChange::enregistrer(2850, $this->ids['comptable']);
        $this->assertSame(['CDF', 'USD'], Monnaie::devisesAcceptees(TauxChange::actuel()));
    }

    public function testLeTauxEnVigueurDependDeLaDateEtLHistoriqueEstConserve(): void
    {
        TauxChange::enregistrer(2820, $this->ids['comptable'], 'USD', '2026-09-01 08:00:00');
        TauxChange::enregistrer(2850, $this->ids['comptable'], 'USD', '2026-09-20 08:00:00');

        $this->assertSame('2820.000000', TauxChange::enVigueur('2026-09-10 12:00:00')['taux']);
        $this->assertSame('2850.000000', TauxChange::enVigueur('2026-09-25 12:00:00')['taux']);
        $this->assertNull(TauxChange::enVigueur('2026-08-01 12:00:00'));
        $this->assertCount(2, TauxChange::historique());
    }

    public function testUnPaiementEnDollarsConserveLeMontantVerseEtLeTaux(): void
    {
        $v = Monnaie::convertir(40.00, 'USD', 2850, 150000);
        $id = Paiement::creer([
            'reference' => Paiement::genererReference(), 'montant' => $v['montant_base'], 'devise_versee' => $v['devise'],
            'montant_verse' => $v['montant_verse'], 'taux_applique' => $v['taux'], 'mode' => 'especes', 'statut' => 'reussi',
            'id_frais' => $this->ids['frais'], 'id_comptable' => $this->ids['comptable'],
        ]);
        $p = Paiement::trouver($id);
        $this->assertSame(['114000.00', 'USD', '40.00', '2850.000000'], [$p['montant'], $p['devise_versee'], $p['montant_verse'], $p['taux_applique']]);
        $this->assertSame("40,00\u{00A0}USD (114\u{00A0}000\u{00A0}FC)", Monnaie::libelleVersement($p));
    }
}
