<?php
declare(strict_types=1);

/**
 * Passerelle de paiement simulée (sandbox locale), pour démontrer le paiement
 * électronique sans connexion Internet.
 *
 * - initierTransaction() enregistre la transaction dans storage/simulateur/ et renvoie
 *   l'URL de public/simulateur/ (téléphone Mobile Money ou page de carte bancaire) ;
 * - la page du simulateur envoie ensuite à public/callback.php une notification JSON
 *   signée en HMAC-SHA256 (en-tête X-Signature), comme le ferait un vrai prestataire.
 */
final class SimulateurPasserelle implements PasserellePaiement
{
    private const DOSSIER = RACINE . '/storage/simulateur';

    public function initierTransaction(float $montant, string $mode, string $reference, array $client): array
    {
        if (!in_array($mode, ['mobile_money', 'carte'], true)) {
            throw new InvalidArgumentException("Mode de paiement non pris en charge par la passerelle : $mode");
        }
        if (!preg_match(Paiement::FORMAT_REFERENCE, $reference)) {
            throw new InvalidArgumentException('Référence de paiement invalide.');
        }

        $transaction = [
            'transaction' => 'SIM-' . strtoupper(bin2hex(random_bytes(5))),
            'reference'   => $reference,
            'montant'     => round($montant, 2),
            'devise'      => $client['devise'] ?? DEVISE,
            'mode'        => $mode,
            'operateur'   => $client['operateur'] ?? null,
            'client'      => [
                'nom'       => $client['nom'] ?? '',
                'telephone' => $client['telephone'] ?? '',
            ],
            'marchand'    => APP_NOM,
            'url_retour'  => $client['url_retour'] ?? null,
            'jeton'       => bin2hex(random_bytes(16)), // protège la page du simulateur contre les envois forgés
            'etat'        => 'en_attente',
            'cree_le'     => date('Y-m-d H:i:s'),
        ];
        self::enregistrer($transaction);
        journaliser('app', "Passerelle simulée : transaction {$transaction['transaction']} initiée pour $reference ($mode, {$transaction['montant']} {$transaction['devise']})");

        return [
            'transaction'  => $transaction['transaction'],
            'url_paiement' => url('/simulateur/', ['ref' => $reference]),
        ];
    }

    public function verifierSignature(string $corps, string $signature): bool
    {
        return $signature !== '' && hash_equals(self::signer($corps), $signature);
    }

    // --- Outils utilisés par la page du simulateur ----------------------------------

    /** Signature HMAC-SHA256 d'un corps de notification avec le secret partagé. */
    public static function signer(string $corps): string
    {
        return hash_hmac('sha256', $corps, PAYMENT_WEBHOOK_SECRET);
    }

    public static function transaction(string $reference): ?array
    {
        if (!preg_match(Paiement::FORMAT_REFERENCE, $reference)) {
            return null;
        }
        $fichier = self::DOSSIER . '/' . $reference . '.json';
        if (!is_file($fichier)) {
            return null;
        }
        $donnees = json_decode((string) file_get_contents($fichier), true);
        return is_array($donnees) ? $donnees : null;
    }

    public static function enregistrer(array $transaction): void
    {
        if (!is_dir(self::DOSSIER)) {
            mkdir(self::DOSSIER, 0775, true);
        }
        file_put_contents(
            self::DOSSIER . '/' . $transaction['reference'] . '.json',
            json_encode($transaction, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    /**
     * Envoie la notification de résultat à public/callback.php (appel serveur à serveur),
     * exactement comme un prestataire réel : corps JSON brut + en-tête X-Signature.
     * @return array{code:int, corps:string}
     */
    public static function notifier(string $reference, string $statut, string $raison): array
    {
        $corps = json_encode(['reference' => $reference, 'status' => $statut, 'reason' => $raison], JSON_UNESCAPED_UNICODE);
        return self::envoyer($corps, self::signer($corps));
    }

    /** Envoi HTTP brut d'une notification (utilisé aussi par tests/envoyer_callback.php). */
    public static function envoyer(string $corps, string $signature): array
    {
        $contexte = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nX-Signature: $signature\r\n",
            'content'       => $corps,
            'timeout'       => 15,
            'ignore_errors' => true, // récupérer aussi le corps des réponses 4xx/5xx
        ]]);
        $reponse = @file_get_contents(self::urlCallback(), false, $contexte);
        $code = 0;
        foreach ($http_response_header ?? [] as $entete) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $entete, $m)) {
                $code = (int) $m[1];
            }
        }
        return ['code' => $code, 'corps' => $reponse === false ? '' : $reponse];
    }

    public static function urlCallback(): string
    {
        return rtrim(APP_URL, '/') . '/callback.php';
    }
}
