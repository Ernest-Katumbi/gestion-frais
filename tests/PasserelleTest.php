<?php
declare(strict_types=1);

/**
 * Passerelle de paiement : signature HMAC des notifications et comportement de
 * public/callback.php (exécuté pour de vrai avec php-cgi, sur la base de test).
 */
final class PasserelleTest extends BaseDeTest
{
    // --- Signature ----------------------------------------------------------------------

    public function testLaFonctionPasserelleRenvoieLeSimulateurConfigure(): void
    {
        $this->assertInstanceOf(PasserellePaiement::class, passerelle());
        $this->assertInstanceOf(SimulateurPasserelle::class, passerelle());
        $this->assertSame(passerelle(), passerelle());
    }

    public function testUneSignatureValideEstAcceptee(): void
    {
        $corps = '{"reference":"PAY-20260924-ABC123","status":"SUCCESS","reason":""}';
        $this->assertTrue(passerelle()->verifierSignature($corps, hash_hmac('sha256', $corps, PAYMENT_WEBHOOK_SECRET)));
    }

    public function testUneSignatureInvalideEstRefusee(): void
    {
        $corps = '{"reference":"PAY-20260924-ABC123","status":"SUCCESS","reason":""}';
        $signature = hash_hmac('sha256', $corps, PAYMENT_WEBHOOK_SECRET);

        $this->assertFalse(passerelle()->verifierSignature($corps, hash_hmac('sha256', $corps, 'autre-secret')), 'mauvais secret');
        $this->assertFalse(passerelle()->verifierSignature(str_replace('SUCCESS', 'FAILED', $corps), $signature), 'corps modifié');
        $this->assertFalse(passerelle()->verifierSignature($corps, ''), 'signature absente');
    }

    // --- public/callback.php --------------------------------------------------------

    public function testUnCallbackASignatureInvalideEstRejeteSansRienModifier(): void
    {
        $reference = $this->paiementEnAttente(60.00);
        $corps = json_encode(['reference' => $reference, 'status' => 'SUCCESS', 'reason' => '']);

        $reponse = $this->appelerCallback($corps, hash_hmac('sha256', $corps, 'secret-inconnu'));

        $this->assertSame(401, $reponse['code']);
        $this->assertSame('en_attente', Paiement::trouverParReference($reference)['statut']);
        $this->assertSame('0.00', $this->frais()['montant_paye']);
    }

    public function testLeMemeCallbackEnvoyeDeuxFoisNestTraiteQuUneFois(): void
    {
        $reference = $this->paiementEnAttente(60.00);
        $corps = json_encode(['reference' => $reference, 'status' => 'SUCCESS', 'reason' => '']);
        $signature = SimulateurPasserelle::signer($corps);

        $premier = $this->appelerCallback($corps, $signature);
        $second = $this->appelerCallback($corps, $signature);

        $this->assertSame(200, $premier['code']);
        $this->assertStringContainsString('traitée', $premier['corps']);
        $this->assertSame(200, $second['code']);
        $this->assertStringContainsString('déjà traitée', $second['corps']);

        $this->assertSame('reussi', Paiement::trouverParReference($reference)['statut']);
        $this->assertSame(['60.00', 'partiel'], [$this->frais()['montant_paye'], $this->frais()['statut']], 'versement compté une seule fois');
        $this->assertSame(1, (int) $this->valeur('SELECT COUNT(*) FROM recu'), 'un seul reçu');
        $this->assertSame(1, (int) $this->valeur("SELECT COUNT(*) FROM notification WHERE type = 'paiement'"), 'une seule notification');
    }

    public function testUnCallbackEchoueNeModifiePasLeFrais(): void
    {
        $reference = $this->paiementEnAttente(150.00);
        $corps = json_encode(['reference' => $reference, 'status' => 'FAILED', 'reason' => 'Solde insuffisant']);

        $reponse = $this->appelerCallback($corps, SimulateurPasserelle::signer($corps));

        $this->assertSame(200, $reponse['code']);
        $this->assertSame('echoue', Paiement::trouverParReference($reference)['statut']);
        $this->assertSame(['0.00', 'impaye'], [$this->frais()['montant_paye'], $this->frais()['statut']]);
        $this->assertSame(0, (int) $this->valeur('SELECT COUNT(*) FROM recu'));
        $this->assertStringContainsString('Solde insuffisant', (string) $this->valeur('SELECT message FROM notification'));
    }

    public function testUnCallbackPourUneReferenceInconnueRenvoie404(): void
    {
        $corps = json_encode(['reference' => 'PAY-20260101-ZZZZZZ', 'status' => 'SUCCESS', 'reason' => '']);
        $this->assertSame(404, $this->appelerCallback($corps, SimulateurPasserelle::signer($corps))['code']);
    }

    // --- Outils ------------------------------------------------------------------------

    private function paiementEnAttente(float $montant): string
    {
        $reference = Paiement::genererReference();
        Paiement::creer([
            'reference' => $reference, 'montant' => $montant, 'mode' => 'mobile_money',
            'statut' => 'en_attente', 'id_frais' => $this->ids['frais'],
        ]);
        return $reference;
    }

    /**
     * Exécute public/callback.php comme le ferait Apache (interface CGI) :
     * corps brut sur l'entrée standard, en-tête X-Signature, base de test.
     * @return array{code:int, corps:string}
     */
    private function appelerCallback(string $corps, string $signature): array
    {
        $cgi = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'php-cgi.exe' : 'php-cgi');
        if (!is_file($cgi)) {
            $this->markTestSkipped('php-cgi introuvable : test du callback ignoré.');
        }
        $script = RACINE . '/public/callback.php';
        $environnement = array_merge(getenv(), [
            'REQUEST_METHOD'    => 'POST',
            'CONTENT_TYPE'      => 'application/json',
            'CONTENT_LENGTH'    => (string) strlen($corps),
            'HTTP_X_SIGNATURE'  => $signature,
            'SCRIPT_FILENAME'   => $script,
            'SCRIPT_NAME'       => '/callback.php',
            'REQUEST_URI'       => '/callback.php',
            'REDIRECT_STATUS'   => '200',
            'GATEWAY_INTERFACE' => 'CGI/1.1',
            'SERVER_PROTOCOL'   => 'HTTP/1.1',
            'REMOTE_ADDR'       => '127.0.0.1',
        ]);
        $processus = proc_open([$cgi, '-q'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubes, RACINE, $environnement);
        fwrite($tubes[0], $corps);
        fclose($tubes[0]);
        $sortie = (string) stream_get_contents($tubes[1]);
        fclose($tubes[1]);
        fclose($tubes[2]);
        proc_close($processus);

        [$entetes, $reponse] = array_pad(preg_split("/\r?\n\r?\n/", $sortie, 2), 2, '');
        $code = preg_match('/^Status:\s*(\d{3})/mi', $entetes, $m) ? (int) $m[1] : 200;
        return ['code' => $code, 'corps' => $reponse];
    }
}
