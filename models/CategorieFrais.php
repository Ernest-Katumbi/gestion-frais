<?php
declare(strict_types=1);

/**
 * Modèle « catégorie de frais » : minerval, inscription, examen…
 */
final class CategorieFrais
{
    public const PERIODICITES = [
        'unique'        => 'Unique',
        'mensuelle'     => 'Mensuelle',
        'trimestrielle' => 'Trimestrielle',
        'annuelle'      => 'Annuelle',
    ];

    /** Toutes les catégories, avec le nombre de frais affectés et le montant encaissé. */
    public static function toutes(): array
    {
        $requete = Database::get()->prepare(
            'SELECT c.*, COUNT(f.id_frais) AS nb_frais, COALESCE(SUM(f.montant_paye), 0) AS total_paye
             FROM categorie_frais c LEFT JOIN frais f ON f.id_categorie = c.id_categorie
             GROUP BY c.id_categorie
             ORDER BY c.libelle'
        );
        $requete->execute();
        return $requete->fetchAll();
    }

    /** Liste id => libellé pour les listes déroulantes. */
    public static function options(): array
    {
        $options = [];
        foreach (self::toutes() as $c) {
            $options[(int) $c['id_categorie']] = $c['libelle'];
        }
        return $options;
    }

    public static function trouver(int $id): ?array
    {
        $requete = Database::get()->prepare('SELECT * FROM categorie_frais WHERE id_categorie = ?');
        $requete->execute([$id]);
        return $requete->fetch() ?: null;
    }

    public static function codeExiste(string $code, ?int $saufId = null): bool
    {
        $requete = Database::get()->prepare('SELECT COUNT(*) FROM categorie_frais WHERE code = ? AND id_categorie <> ?');
        $requete->execute([$code, $saufId ?? 0]);
        return (int) $requete->fetchColumn() > 0;
    }

    public static function creer(array $d): int
    {
        $requete = Database::get()->prepare(
            'INSERT INTO categorie_frais (code, libelle, montant_defaut, periodicite) VALUES (?, ?, ?, ?)'
        );
        $requete->execute([$d['code'], $d['libelle'], $d['montant_defaut'], $d['periodicite']]);
        return (int) Database::get()->lastInsertId();
    }

    /**
     * Modification. Le montant par défaut ne s'applique qu'aux affectations futures :
     * les frais déjà créés gardent leur montant.
     */
    public static function modifier(int $id, array $d): void
    {
        $requete = Database::get()->prepare(
            'UPDATE categorie_frais SET code = ?, libelle = ?, montant_defaut = ?, periodicite = ? WHERE id_categorie = ?'
        );
        $requete->execute([$d['code'], $d['libelle'], $d['montant_defaut'], $d['periodicite'], $id]);
    }

    /** Une catégorie est « utilisée » dès qu'un frais y est rattaché. */
    public static function estUtilisee(int $id): bool
    {
        $requete = Database::get()->prepare('SELECT COUNT(*) FROM frais WHERE id_categorie = ?');
        $requete->execute([$id]);
        return (int) $requete->fetchColumn() > 0;
    }

    public static function supprimer(int $id): void
    {
        $requete = Database::get()->prepare('DELETE FROM categorie_frais WHERE id_categorie = ?');
        $requete->execute([$id]);
    }
}
