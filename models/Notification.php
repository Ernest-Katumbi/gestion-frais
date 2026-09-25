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
