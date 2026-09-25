<?php
declare(strict_types=1);

/**
 * Modèle « élève » : chaque élève appartient à une classe et est rattaché à un parent.
 */
final class Eleve
{
    /** Format des matricules : IO-2026-001. */
    public const FORMAT_MATRICULE = '/^[A-Z0-9]+(-[A-Z0-9]+)*$/';

    private const SELECT_DETAIL =
        'SELECT e.*, c.libelle AS classe, c.niveau, c.section,
                p.nom AS parent_nom, p.email AS parent_email, p.telephone AS parent_telephone,
                p.adresse AS parent_adresse, p.statut AS parent_statut
         FROM eleve e
         JOIN classe c ON c.id_classe = e.id_classe
         JOIN utilisateur p ON p.id_utilisateur = e.id_parent';

    /**
     * Liste paginée : recherche sur le matricule, le nom, le prénom ou le nom du parent.
     * @return array{lignes: list<array>, total: int}
     */
    public static function lister(string $recherche = '', int $idClasse = 0, int $page = 1, int $parPage = 15): array
    {
        $conditions = [];
        $parametres = [];
        if ($recherche !== '') {
            $conditions[] = "(e.matricule LIKE ? OR e.nom LIKE ? OR e.prenom LIKE ?
                             OR CONCAT(e.nom, ' ', e.prenom) LIKE ? OR CONCAT(e.prenom, ' ', e.nom) LIKE ? OR p.nom LIKE ?)";
            array_push($parametres, ...array_fill(0, 6, motifLike($recherche)));
        }
        if ($idClasse > 0) {
            $conditions[] = 'e.id_classe = ?';
            $parametres[] = $idClasse;
        }
        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

        $db = Database::get();
        $compte = $db->prepare(
            'SELECT COUNT(*) FROM eleve e JOIN utilisateur p ON p.id_utilisateur = e.id_parent' . $where
        );
        $compte->execute($parametres);
        $total = (int) $compte->fetchColumn();

        $requete = $db->prepare(self::SELECT_DETAIL . $where . ' ORDER BY e.nom, e.prenom LIMIT ? OFFSET ?');
        $i = 1;
        foreach ($parametres as $valeur) {
            $requete->bindValue($i++, $valeur, is_int($valeur) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $requete->bindValue($i++, $parPage, PDO::PARAM_INT);
        $requete->bindValue($i, ($page - 1) * $parPage, PDO::PARAM_INT);
        $requete->execute();

        return ['lignes' => $requete->fetchAll(), 'total' => $total];
    }

    /** Élève avec sa classe et son parent. */
    public static function trouver(int $id): ?array
    {
        $requete = Database::get()->prepare(self::SELECT_DETAIL . ' WHERE e.id_eleve = ?');
        $requete->execute([$id]);
        return $requete->fetch() ?: null;
    }

    /** Enfants d'un parent (contrôle de propriété de l'espace parent). */
    public static function parParent(int $idParent): array
    {
        $requete = Database::get()->prepare(self::SELECT_DETAIL . ' WHERE e.id_parent = ? ORDER BY e.prenom');
        $requete->execute([$idParent]);
        return $requete->fetchAll();
    }

    public static function matriculeExiste(string $matricule, ?int $saufId = null): bool
    {
        $requete = Database::get()->prepare('SELECT COUNT(*) FROM eleve WHERE matricule = ? AND id_eleve <> ?');
        $requete->execute([$matricule, $saufId ?? 0]);
        return (int) $requete->fetchColumn() > 0;
    }

    /** Matricule suivant proposé à l'inscription : IO-{année}-{numéro sur 3 chiffres}. */
    public static function prochainMatricule(): string
    {
        $prefixe = 'IO-' . date('Y') . '-';
        $requete = Database::get()->prepare(
            'SELECT MAX(CAST(SUBSTRING(matricule, ?) AS UNSIGNED)) FROM eleve WHERE matricule LIKE ?'
        );
        $requete->execute([strlen($prefixe) + 1, $prefixe . '%']);
        return $prefixe . str_pad((string) ((int) $requete->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
    }

    public static function creer(array $d): int
    {
        $requete = Database::get()->prepare(
            'INSERT INTO eleve (matricule, nom, prenom, date_inscription, id_classe, id_parent) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $requete->execute([$d['matricule'], $d['nom'], $d['prenom'], $d['date_inscription'], $d['id_classe'], $d['id_parent']]);
        return (int) Database::get()->lastInsertId();
    }

    public static function modifier(int $id, array $d): void
    {
        $requete = Database::get()->prepare(
            'UPDATE eleve SET matricule = ?, nom = ?, prenom = ?, date_inscription = ?, id_classe = ?, id_parent = ?
             WHERE id_eleve = ?'
        );
        $requete->execute([$d['matricule'], $d['nom'], $d['prenom'], $d['date_inscription'], $d['id_classe'], $d['id_parent'], $id]);
    }

    /** Un élève ayant au moins un paiement enregistré (quel que soit son statut) ne peut pas être supprimé. */
    public static function aDesPaiements(int $id): bool
    {
        $requete = Database::get()->prepare(
            'SELECT COUNT(*) FROM paiement pa JOIN frais f ON f.id_frais = pa.id_frais WHERE f.id_eleve = ?'
        );
        $requete->execute([$id]);
        return (int) $requete->fetchColumn() > 0;
    }

    /**
     * Supprime un élève sans paiement : ses frais (tous impayés, donc sans historique)
     * sont supprimés avec lui dans la même transaction.
     */
    public static function supprimer(int $id): void
    {
        $db = Database::get();
        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM frais WHERE id_eleve = ?')->execute([$id]);
            $db->prepare('DELETE FROM eleve WHERE id_eleve = ?')->execute([$id]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function compter(): int
    {
        $requete = Database::get()->prepare('SELECT COUNT(*) FROM eleve');
        $requete->execute();
        return (int) $requete->fetchColumn();
    }

    /** Derniers élèves inscrits (tableau de bord). */
    public static function derniers(int $nombre = 5): array
    {
        $requete = Database::get()->prepare(self::SELECT_DETAIL . ' ORDER BY e.date_inscription DESC, e.id_eleve DESC LIMIT ?');
        $requete->bindValue(1, $nombre, PDO::PARAM_INT);
        $requete->execute();
        return $requete->fetchAll();
    }
}
