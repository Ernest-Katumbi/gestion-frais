<?php
declare(strict_types=1);

/**
 * Références de paiement : format PAY-AAAAMMJJ-XXXXXX et unicité.
 */
final class PaiementTest extends BaseDeTest
{
    public function testLaReferenceRespecteLeFormat(): void
    {
        $reference = Paiement::genererReference();
        $this->assertMatchesRegularExpression('/^PAY-\d{8}-[A-Z0-9]{6}$/', $reference);
        $this->assertMatchesRegularExpression(Paiement::FORMAT_REFERENCE, $reference);
    }

    public function testLaReferenceContientLaDateDuPaiement(): void
    {
        $this->assertStringStartsWith('PAY-' . date('Ymd') . '-', Paiement::genererReference());
        $this->assertStringStartsWith('PAY-20260924-', Paiement::genererReference(new DateTimeImmutable('2026-09-24')));
    }

    public function testMilleReferencesSontToutesDifferentes(): void
    {
        $references = [];
        for ($i = 0; $i < 1000; $i++) {
            $references[] = Paiement::genererReference();
        }
        $this->assertCount(1000, array_unique($references));
    }

    public function testUneReferenceDejaUtiliseeEnBaseNestJamaisProposee(): void
    {
        $reference = Paiement::genererReference();
        Paiement::creer([
            'reference' => $reference, 'montant' => 10, 'mode' => 'especes', 'statut' => 'reussi',
            'id_frais' => $this->ids['frais'], 'id_comptable' => $this->ids['comptable'],
        ]);
        $this->assertNotNull(Paiement::trouverParReference($reference));
        $this->assertNotSame($reference, Paiement::genererReference());
    }

    public function testLaBaseRefuseUneReferenceEnDouble(): void
    {
        $donnees = [
            'reference' => 'PAY-20260924-ABC123', 'montant' => 10, 'mode' => 'especes', 'statut' => 'reussi',
            'id_frais' => $this->ids['frais'], 'id_comptable' => $this->ids['comptable'],
        ];
        Paiement::creer($donnees);
        $this->expectException(PDOException::class);
        Paiement::creer($donnees);
    }
}
