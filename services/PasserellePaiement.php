<?php
declare(strict_types=1);

/**
 * Contrat commun à toute passerelle de paiement électronique (Mobile Money, carte).
 *
 * L'application ne dépend que de cette interface : on passe du simulateur local
 * à un prestataire réel en changeant PAYMENT_DRIVER dans config.php, sans modifier
 * le reste du code. L'implémentation active est obtenue par la fonction passerelle().
 */
interface PasserellePaiement
{
    /**
     * Demande à la passerelle d'exécuter une transaction.
     *
     * @param float  $montant   montant à débiter, dans la devise indiquée par $client['devise']
     * @param string $mode      'mobile_money' ou 'carte'
     * @param string $reference référence unique du paiement (PAY-AAAAMMJJ-XXXXXX)
     * @param array  $client    devise (CDF, USD…), nom, email, telephone, url_retour…
     * @return array ['url_paiement' => page où le client confirme, 'transaction' => identifiant chez la passerelle]
     */
    public function initierTransaction(float $montant, string $mode, string $reference, array $client): array;

    /**
     * Vérifie que la notification reçue a bien été émise par la passerelle
     * (signature HMAC-SHA256 du corps brut de la requête).
     */
    public function verifierSignature(string $corps, string $signature): bool;
}
