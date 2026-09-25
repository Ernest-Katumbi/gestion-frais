<?php
declare(strict_types=1);

/**
 * Modèle « utilisateur » : administrateurs, comptables et parents.
 */
final class User
{
    public const ROLES = [
        'admin'     => 'Administrateur',
        'comptable' => 'Comptable',
        'parent'    => 'Parent',
    ];

    public static function trouverParEmail(string $email): ?array
    {
        $requete = Database::get()->prepare('SELECT * FROM utilisateur WHERE email = ?');
        $requete->execute([mb_strtolower(trim($email))]);
        return $requete->fetch() ?: null;
    }

    public static function trouver(int $id): ?array
    {
        $requete = Database::get()->prepare('SELECT * FROM utilisateur WHERE id_utilisateur = ?');
        $requete->execute([$id]);
        return $requete->fetch() ?: null;
    }

    public static function emailExiste(string $email, ?int $saufId = null): bool
    {
        $requete = Database::get()->prepare(
            'SELECT COUNT(*) FROM utilisateur WHERE email = ? AND id_utilisateur <> ?'
        );
        $requete->execute([mb_strtolower(trim($email)), $saufId ?? 0]);
        return (int) $requete->fetchColumn() > 0;
    }

    /**
     * Liste paginée avec recherche (nom, e-mail, téléphone) et filtres.
     * @return array{lignes: list<array>, total: int}
     */
    public static function lister(string $recherche = '', string $role = '', string $statut = '', int $page = 1, int $parPage = 15): array
    {
        $conditions = [];
        $parametres = [];
        if ($recherche !== '') {
            $conditions[] = '(nom LIKE ? OR email LIKE ? OR telephone LIKE ?)';
            array_push($parametres, motifLike($recherche), motifLike($recherche), motifLike($recherche));
        }
        if (isset(self::ROLES[$role])) {
            $conditions[] = 'role = ?';
            $parametres[] = $role;
        }
        if (in_array($statut, ['actif', 'inactif'], true)) {
            $conditions[] = 'statut = ?';
            $parametres[] = $statut;
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $db = Database::get();
        $compte = $db->prepare("SELECT COUNT(*) FROM utilisateur $where");
        $compte->execute($parametres);
        $total = (int) $compte->fetchColumn();

        $requete = $db->prepare(
            "SELECT u.*, (SELECT COUNT(*) FROM eleve e WHERE e.id_parent = u.id_utilisateur) AS nb_enfants
             FROM utilisateur u $where
             ORDER BY u.statut ASC, u.nom ASC
             LIMIT ? OFFSET ?"
        );
        $i = 1;
        foreach ($parametres as $valeur) {
            $requete->bindValue($i++, $valeur);
        }
        $requete->bindValue($i++, $parPage, PDO::PARAM_INT);
        $requete->bindValue($i, ($page - 1) * $parPage, PDO::PARAM_INT);
        $requete->execute();

        return ['lignes' => $requete->fetchAll(), 'total' => $total];
    }

    /** Crée un utilisateur ; le mot de passe est haché (bcrypt). Renvoie son identifiant. */
    public static function creer(array $d): int
    {
        $requete = Database::get()->prepare(
            'INSERT INTO utilisateur (nom, email, mot_de_passe, role, telephone, adresse, statut)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $requete->execute([
            $d['nom'],
            mb_strtolower(trim($d['email'])),
            password_hash($d['mot_de_passe'], PASSWORD_DEFAULT),
            $d['role'],
            ($d['telephone'] ?? '') !== '' ? $d['telephone'] : null,
            ($d['adresse'] ?? '') !== '' ? $d['adresse'] : null,
            $d['statut'] ?? 'actif',
        ]);
        return (int) Database::get()->lastInsertId();
    }

    public static function modifier(int $id, array $d): void
    {
        $requete = Database::get()->prepare(
            'UPDATE utilisateur SET nom = ?, email = ?, role = ?, telephone = ?, adresse = ?, statut = ?
             WHERE id_utilisateur = ?'
        );
        $requete->execute([
            $d['nom'],
            mb_strtolower(trim($d['email'])),
            $d['role'],
            ($d['telephone'] ?? '') !== '' ? $d['telephone'] : null,
            ($d['adresse'] ?? '') !== '' ? $d['adresse'] : null,
            $d['statut'],
            $id,
        ]);
    }

    public static function changerMotDePasse(int $id, string $motDePasse): void
    {
        $requete = Database::get()->prepare('UPDATE utilisateur SET mot_de_passe = ? WHERE id_utilisateur = ?');
        $requete->execute([password_hash($motDePasse, PASSWORD_DEFAULT), $id]);
    }

    public static function changerStatut(int $id, string $statut): void
    {
        $requete = Database::get()->prepare('UPDATE utilisateur SET statut = ? WHERE id_utilisateur = ?');
        $requete->execute([$statut, $id]);
    }

    /**
     * Un utilisateur a un historique s'il est parent d'un élève, s'il a encaissé
     * un paiement ou s'il a reçu des notifications : il ne peut alors qu'être désactivé.
     */
    public static function aUnHistorique(int $id): bool
    {
        $requete = Database::get()->prepare(
            'SELECT (SELECT COUNT(*) FROM eleve WHERE id_parent = ?)
                  + (SELECT COUNT(*) FROM paiement WHERE id_comptable = ?)
                  + (SELECT COUNT(*) FROM notification WHERE id_utilisateur = ?)'
        );
        $requete->execute([$id, $id, $id]);
        return (int) $requete->fetchColumn() > 0;
    }

    public static function supprimer(int $id): void
    {
        $requete = Database::get()->prepare('DELETE FROM utilisateur WHERE id_utilisateur = ?');
        $requete->execute([$id]);
    }

    public static function compterAdminsActifs(): int
    {
        $requete = Database::get()->prepare('SELECT COUNT(*) FROM utilisateur WHERE role = ? AND statut = ?');
        $requete->execute(['admin', 'actif']);
        return (int) $requete->fetchColumn();
    }

    /** Nombre d'utilisateurs actifs par rôle, ex. ['admin' => 1, 'comptable' => 1, 'parent' => 4]. */
    public static function compterParRole(): array
    {
        $resultat = array_fill_keys(array_keys(self::ROLES), 0);
        $requete = Database::get()->prepare('SELECT role, COUNT(*) AS n FROM utilisateur WHERE statut = ? GROUP BY role');
        $requete->execute(['actif']);
        $lignes = $requete->fetchAll();
        foreach ($lignes as $ligne) {
            $resultat[$ligne['role']] = (int) $ligne['n'];
        }
        return $resultat;
    }

    public static function compterInactifs(): int
    {
        $requete = Database::get()->prepare('SELECT COUNT(*) FROM utilisateur WHERE statut = ?');
        $requete->execute(['inactif']);
        return (int) $requete->fetchColumn();
    }

    /** Derniers comptes créés (tableau de bord). */
    public static function derniers(int $nombre = 5): array
    {
        $requete = Database::get()->prepare('SELECT * FROM utilisateur ORDER BY date_creation DESC, id_utilisateur DESC LIMIT ?');
        $requete->bindValue(1, $nombre, PDO::PARAM_INT);
        $requete->execute();
        return $requete->fetchAll();
    }
}
