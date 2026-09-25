<?php
declare(strict_types=1);

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Génération des reçus de paiement : enregistrement en base, PDF (Dompdf)
 * et QR code pointant vers l'URL de vérification signée (HMAC-SHA256).
 */
final class GenerateurRecu
{
    /** Dossier des PDF, hors du webroot : les reçus ne sont servis qu'après contrôle d'accès. */
    public const DOSSIER = RACINE . '/recus';

    /**
     * Génère le reçu d'un paiement réussi (base + fichier PDF) et le renvoie.
     * Idempotent : si le reçu existe déjà, il est renvoyé tel quel.
     * À appeler dans la transaction qui valide le paiement.
     *
     * @throws DomainException si le paiement n'existe pas ou n'est pas réussi
     */
    public static function generer(int $idPaiement): array
    {
        $paiement = Paiement::trouver($idPaiement);
        if ($paiement === null || $paiement['statut'] !== 'reussi') {
            throw new DomainException('Un reçu ne peut être émis que pour un paiement réussi.');
        }
        $existant = Recu::trouverParPaiement($idPaiement);
        if ($existant !== null) {
            return $existant;
        }

        $annee = (int) (new DateTimeImmutable($paiement['date_paiement']))->format('Y');
        $recu = Recu::creer($idPaiement, $annee, [self::class, 'urlVerification']);
        self::ecrirePdf(Paiement::trouver($idPaiement));
        return $recu;
    }

    /** Chemin du PDF ; il est regénéré s'il a disparu du disque. */
    public static function cheminPdf(array $paiement): string
    {
        $chemin = self::DOSSIER . '/' . basename((string) $paiement['fichier_pdf']);
        if (!is_file($chemin)) {
            self::ecrirePdf($paiement);
        }
        return $chemin;
    }

    // --- Vérification des reçus ------------------------------------------------------

    /** Signature HMAC du numéro de reçu (tronquée à 32 caractères pour alléger le QR code). */
    public static function signature(string $numero): string
    {
        return substr(hash_hmac('sha256', $numero, RECU_SECRET), 0, 32);
    }

    public static function verifierSignature(string $numero, string $signature): bool
    {
        return hash_equals(self::signature($numero), $signature);
    }

    /** URL publique de vérification encodée dans le QR code. */
    public static function urlVerification(string $numero): string
    {
        return urlAbsolue('/verifier-recu.php', ['n' => $numero, 'h' => self::signature($numero)]);
    }

    /** Image PNG du QR code, en data URI (utilisable dans le PDF comme dans une page). */
    public static function qrCode(string $contenu, int $echelle = 5): string
    {
        $options = new QROptions([
            'outputType'   => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => true,
            'eccLevel'     => EccLevel::M,
            'scale'        => $echelle,
            'quietzoneSize' => 2,
        ]);
        return (new QRCode($options))->render($contenu);
    }

    // --- PDF ----------------------------------------------------------------------

    /** Contenu binaire du PDF d'un paiement (vue views/pdf/recu.php, format A5). */
    public static function pdf(array $paiement): string
    {
        $html = View::rendre('pdf/recu', [
            'p'    => $paiement,
            'qr'   => self::qrCode((string) $paiement['code_qr']),
            'logo' => 'data:image/svg+xml;base64,' . base64_encode((string) file_get_contents(RACINE . '/public/assets/img/logo.svg')),
        ], null);
        return self::rendrePdf($html, 'A5');
    }

    /** Conversion HTML → PDF avec Dompdf (aucune ressource distante). */
    public static function rendrePdf(string $html, string $format = 'A4', string $orientation = 'portrait'): string
    {
        $options = new Options([
            'defaultFont'     => 'DejaVu Sans',
            'isRemoteEnabled' => false,
            'chroot'          => RACINE,
            'tempDir'         => RACINE . '/storage/cache',
        ]);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($format, $orientation);
        $dompdf->render();
        return (string) $dompdf->output();
    }

    private static function ecrirePdf(array $paiement): void
    {
        if (!is_dir(self::DOSSIER)) {
            mkdir(self::DOSSIER, 0775, true);
        }
        $chemin = self::DOSSIER . '/' . basename((string) $paiement['fichier_pdf']);
        if (file_put_contents($chemin, self::pdf($paiement), LOCK_EX) === false) {
            throw new RuntimeException("Impossible d'écrire le reçu $chemin.");
        }
    }
}
