<?php
declare(strict_types=1);

/**
 * Modèle « reçu » : un reçu unique par paiement réussi (numéro REC-AAAA-NNNNNN).
 */
final class Recu
{
    public static function trouver(int $id): ?array
    {
        $requete = Database::get()->prepare('SELECT * FROM recu WHERE id_recu = ?');
        $requete->execute([$id]);
        return $requete->fetch() ?: null;
    }

    public static function trouverParPaiement(int $idPaiement): ?array
    {
        $requete = Database::get()->prepare('SELECT * FROM recu WHERE id_paiement = ?');
        $requete->execute([$idPaiement]);
        return $requete->fetch() ?: null;
    }

    public static function trouverParNumero(string $numero): ?array
    {
        $requete = Database::get()->prepare('SELECT * FROM recu WHERE numero = ?');
        $requete->execute([$numero]);
        return $requete->fetch() ?: null;
    }

    /**
     * Crée le reçu d'un paiement. Le numéro dépend de l'identifiant attribué par la base,
     * ce qui garantit son unicité même en cas d'encaissements simultanés.
     * @return array le reçu créé
     */
    public static function creer(int $idPaiement, int $annee, callable $urlVerification): array
    {
        $db = Database::get();
        $provisoire = 'TMP-' . bin2hex(random_bytes(8));
        $db->prepare('INSERT INTO recu (numero, code_qr, fichier_pdf, id_paiement) VALUES (?, ?, ?, ?)')
           ->execute([$provisoire, '', '', $idPaiement]);
        $id = (int) $db->lastInsertId();

        $numero = sprintf('REC-%d-%06d', $annee, $id);
        $db->prepare('UPDATE recu SET numero = ?, code_qr = ?, fichier_pdf = ? WHERE id_recu = ?')
           ->execute([$numero, $urlVerification($numero), $numero . '.pdf', $id]);

        return self::trouver($id);
    }
}
