<?php
declare(strict_types=1);

/**
 * Modèle « taux de change » : nombre d'unités de la devise de base (FC) pour une unité
 * de la devise étrangère (USD). Chaque nouveau taux est une nouvelle ligne : l'historique
 * est conservé et chaque paiement garde le taux qui lui a été appliqué.
 */
final class TauxChange
{
    /** Taux en vigueur (le plus récent), ou null si aucun taux n'a encore été saisi. */
    public static function actuel(string $devise = DEVISE_ETRANGERE): ?array
    {
        return self::enVigueur(date('Y-m-d H:i:s'), $devise);
    }

    /** Taux en vigueur à une date donnée (dernier taux saisi avant cette date). */
    public static function enVigueur(string $date, string $devise = DEVISE_ETRANGERE): ?array
    {
        $requete = Database::get()->prepare(
            'SELECT t.*, u.nom AS saisi_par FROM taux_change t JOIN utilisateur u ON u.id_utilisateur = t.id_utilisateur
             WHERE t.devise = ? AND t.date_application <= ?
             ORDER BY t.date_application DESC, t.id_taux DESC LIMIT 1'
        );
        $requete->execute([$devise, $date]);
        return $requete->fetch() ?: null;
    }

    public static function historique(int $nombre = 30, string $devise = DEVISE_ETRANGERE): array
    {
        $requete = Database::get()->prepare(
            'SELECT t.*, u.nom AS saisi_par FROM taux_change t JOIN utilisateur u ON u.id_utilisateur = t.id_utilisateur
             WHERE t.devise = ? ORDER BY t.date_application DESC, t.id_taux DESC LIMIT ?'
        );
        $requete->bindValue(1, $devise);
        $requete->bindValue(2, $nombre, PDO::PARAM_INT);
        $requete->execute();
        return $requete->fetchAll();
    }

    public static function enregistrer(float $taux, int $idUtilisateur, string $devise = DEVISE_ETRANGERE, ?string $date = null): int
    {
        $requete = Database::get()->prepare(
            'INSERT INTO taux_change (devise, taux, date_application, id_utilisateur) VALUES (?, ?, ?, ?)'
        );
        $requete->execute([$devise, number_format($taux, 6, '.', ''), $date ?? date('Y-m-d H:i:s'), $idUtilisateur]);
        return (int) Database::get()->lastInsertId();
    }
}
