<?php
declare(strict_types=1);

/**
 * Squelette (non utilisé par le prototype) montrant où brancher un prestataire réel
 * de paiement (agrégateur Mobile Money / carte opérant en RDC).
 *
 * Pour l'activer : PAYMENT_DRIVER = 'reel' dans config.php, puis compléter les
 * constantes et les deux méthodes ci-dessous selon la documentation du prestataire.
 * Le reste de l'application (contrôleurs, callback.php) ne change pas.
 */
final class PasserelleReelle implements PasserellePaiement
{
    // Valeurs fournies par le prestataire lors de l'ouverture du compte marchand.
    private const URL_API = 'https://api.prestataire.example/v1';
    private const CLE_API = '';          // à placer en réalité dans config.php, hors du webroot

    public function initierTransaction(float $montant, string $mode, string $reference, array $client): array
    {
        if (self::CLE_API === '') {
            throw new RuntimeException('Passerelle réelle non configurée : renseignez les accès du prestataire ou utilisez PAYMENT_DRIVER = \'simulateur\'.');
        }

        // Exemple de requête (à adapter au prestataire) :
        // POST {URL_API}/payments
        // Authorization: Bearer {CLE_API}
        // {
        //   "amount": 100.00, "currency": "USD", "merchant_reference": "PAY-20260924-7KQ2ZD",
        //   "method": "mobile_money" | "card",
        //   "customer": { "name": ..., "phone": ..., "email": ... },
        //   "callback_url": "https://…/callback.php",   ← notification serveur à serveur
        //   "return_url":   "https://…/parent/paiements/42"
        // }
        // Réponse attendue : identifiant de transaction + URL de paiement (page de carte
        // hébergée par le prestataire, ou confirmation USSD envoyée au téléphone).

        throw new LogicException('Intégration du prestataire réel à implémenter.');
    }

    public function verifierSignature(string $corps, string $signature): bool
    {
        // La plupart des prestataires signent le corps brut en HMAC-SHA256 avec un secret
        // partagé : même principe que le simulateur, seul le secret change.
        return $signature !== '' && hash_equals(hash_hmac('sha256', $corps, PAYMENT_WEBHOOK_SECRET), $signature);
    }
}
