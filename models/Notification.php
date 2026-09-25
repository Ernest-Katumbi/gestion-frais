<?php
declare(strict_types=1);

/**
 * Modèle « notification » : messages affichés sous la cloche de la barre supérieure.
 */
final class Notification
{
    public static function creer(int $idUtilisateur, string $type, string $message): int
    {
        $requete = Database::get()->prepare(
            'INSERT INTO notification (type, message, id_utilisateur) VALUES (?, ?, ?)'
        );
        $requete->execute([$type, mb_substr($message, 0, 255), $idUtilisateur]);
        return (int) Database::get()->lastInsertId();
    }

    public static function compterNonLues(int $idUtilisateur): int
    {
        $requete = Database::get()->prepare('SELECT COUNT(*) FROM notification WHERE id_utilisateur = ? AND lu = FALSE');
        $requete->execute([$idUtilisateur]);
        return (int) $requete->fetchColumn();
    }

    /** @return array{lignes: list<array>, total: int} */
    public static function lister(int $idUtilisateur, int $page = 1, int $parPage = 15): array
    {
        $db = Database::get();
        $compte = $db->prepare('SELECT COUNT(*) FROM notification WHERE id_utilisateur = ?');
        $compte->execute([$idUtilisateur]);

        $requete = $db->prepare(
            'SELECT * FROM notification WHERE id_utilisateur = ? ORDER BY date_envoi DESC, id_notification DESC LIMIT ? OFFSET ?'
        );
        $requete->bindValue(1, $idUtilisateur, PDO::PARAM_INT);
        $requete->bindValue(2, $parPage, PDO::PARAM_INT);
        $requete->bindValue(3, ($page - 1) * $parPage, PDO::PARAM_INT);
        $requete->execute();
        return ['lignes' => $requete->fetchAll(), 'total' => (int) $compte->fetchColumn()];
    }

    /** Marque une notification comme lue — uniquement si elle appartient à l'utilisateur. */
    public static function marquerLue(int $idNotification, int $idUtilisateur): bool
    {
        $requete = Database::get()->prepare('UPDATE notification SET lu = TRUE WHERE id_notification = ? AND id_utilisateur = ?');
        $requete->execute([$idNotification, $idUtilisateur]);
        return $requete->rowCount() > 0;
    }

    public static function toutMarquerLues(int $idUtilisateur): int
    {
        $requete = Database::get()->prepare('UPDATE notification SET lu = TRUE WHERE id_utilisateur = ? AND lu = FALSE');
        $requete->execute([$idUtilisateur]);
        return $requete->rowCount();
    }

    /** Dernière notification d'un utilisateur mentionnant une référence de paiement (motif d'échec). */
    public static function pourReference(int $idUtilisateur, string $reference): ?array
    {
        $requete = Database::get()->prepare(
            'SELECT * FROM notification WHERE id_utilisateur = ? AND message LIKE ? ORDER BY id_notification DESC LIMIT 1'
        );
        $requete->execute([$idUtilisateur, motifLike($reference)]);
        return $requete->fetch() ?: null;
    }

    /** Un rappel identique a-t-il déjà été envoyé aujourd'hui (évite les doublons du cron) ? */
    public static function rappelDejaEnvoye(int $idUtilisateur, string $debutMessage, string $jour): bool
    {
        $requete = Database::get()->prepare(
            "SELECT COUNT(*) FROM notification
             WHERE id_utilisateur = ? AND type = 'echeance' AND DATE(date_envoi) = ? AND message LIKE ?"
        );
        $requete->execute([$idUtilisateur, $jour, addcslashes($debutMessage, '%_\\') . '%']);
        return (int) $requete->fetchColumn() > 0;
    }

    public static function dernieres(int $idUtilisateur, int $nombre = 5): array
    {
        $requete = Database::get()->prepare(
            'SELECT * FROM notification WHERE id_utilisateur = ? ORDER BY date_envoi DESC, id_notification DESC LIMIT ?'
        );
        $requete->bindValue(1, $idUtilisateur, PDO::PARAM_INT);
        $requete->bindValue(2, $nombre, PDO::PARAM_INT);
        $requete->execute();
        return $requete->fetchAll();
    }
}
