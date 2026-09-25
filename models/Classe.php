<?php
declare(strict_types=1);

/**
 * Modèle « classe » : niveau (1re à 6e), section et libellé unique.
 */
final class Classe
{
    public const NIVEAUX = ['1re', '2e', '3e', '4e', '5e', '6e'];

    /** Sections proposées dans le formulaire (saisie libre possible). */
    public const SECTIONS = ['Orientation', 'Scientifique', 'Commerciale', 'Latin-Philo', 'Pédagogique', 'Technique'];

    /** Toutes les classes, avec leur nombre d'élèves, triées par niveau puis section. */
    public static function toutes(string $recherche = ''): array
    {
        $where = '';
        $parametres = [];
        if ($recherche !== '') {
            $where = 'WHERE c.libelle LIKE ? OR c.section LIKE ?';
            $parametres = [motifLike($recherche), motifLike($recherche)];
        }
        $requete = Database::get()->prepare(
            "SELECT c.*, COUNT(e.id_eleve) AS nb_eleves
             FROM classe c LEFT JOIN eleve e ON e.id_classe = c.id_classe
             $where
             GROUP BY c.id_classe
             ORDER BY CAST(c.niveau AS UNSIGNED), c.section, c.libelle"
        );
        $requete->execute($parametres);
        return $requete->fetchAll();
    }

    /** Liste id => libellé pour les listes déroulantes. */
    public static function options(): array
    {
        $options = [];
        foreach (self::toutes() as $classe) {
            $options[(int) $classe['id_classe']] = $classe['libelle'];
        }
        return $options;
    }

    public static function trouver(int $id): ?array
    {
        $requete = Database::get()->prepare(
            'SELECT c.*, (SELECT COUNT(*) FROM eleve e WHERE e.id_classe = c.id_classe) AS nb_eleves
             FROM classe c WHERE c.id_classe = ?'
        );
        $requete->execute([$id]);
        return $requete->fetch() ?: null;
    }

    public static function libelleExiste(string $libelle, ?int $saufId = null): bool
    {
        $requete = Database::get()->prepare('SELECT COUNT(*) FROM classe WHERE libelle = ? AND id_classe <> ?');
        $requete->execute([$libelle, $saufId ?? 0]);
        return (int) $requete->fetchColumn() > 0;
    }

    public static function creer(array $d): int
    {
        $requete = Database::get()->prepare('INSERT INTO classe (niveau, section, libelle) VALUES (?, ?, ?)');
        $requete->execute([$d['niveau'], $d['section'], $d['libelle']]);
        return (int) Database::get()->lastInsertId();
    }

    public static function modifier(int $id, array $d): void
    {
        $requete = Database::get()->prepare('UPDATE classe SET niveau = ?, section = ?, libelle = ? WHERE id_classe = ?');
        $requete->execute([$d['niveau'], $d['section'], $d['libelle'], $id]);
    }

    public static function compterEleves(int $id): int
    {
        $requete = Database::get()->prepare('SELECT COUNT(*) FROM eleve WHERE id_classe = ?');
        $requete->execute([$id]);
        return (int) $requete->fetchColumn();
    }

    /** Suppression : à n'appeler que pour une classe sans élève (vérifié par le contrôleur). */
    public static function supprimer(int $id): void
    {
        $requete = Database::get()->prepare('DELETE FROM classe WHERE id_classe = ?');
        $requete->execute([$id]);
    }

    public static function compter(): int
    {
        $requete = Database::get()->prepare('SELECT COUNT(*) FROM classe');
        $requete->execute();
        return (int) $requete->fetchColumn();
    }
}
