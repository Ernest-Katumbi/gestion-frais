<?php
declare(strict_types=1);

/**
 * Modèle « paiement ». Seuls les paiements au statut « reussi » comptent
 * dans les soldes, les rapports et les reçus.
 */
final class Paiement
{
    public const MODES = ['mobile_money' => 'Mobile Money', 'carte' => 'Carte bancaire', 'especes' => 'Espèces (guichet)'];
    public const STATUTS = ['en_attente' => 'En attente', 'reussi' => 'Réussi', 'echoue' => 'Échoué'];

    /** Format des références : PAY-AAAAMMJJ-XXXXXX. */
    public const FORMAT_REFERENCE = '/^PAY-\d{8}-[A-Z0-9]{6}$/';

    private const SELECT_DETAIL =
        'SELECT pa.*, f.montant AS frais_montant, f.montant_paye AS frais_paye, f.echeance, f.statut AS frais_statut,
                (f.montant - f.montant_paye) AS frais_reste, f.id_categorie,
                c.libelle AS categorie, c.code AS categorie_code,
                e.id_eleve, e.matricule, e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.id_parent,
                cl.libelle AS classe,
                p.nom AS parent_nom, p.email AS parent_email, p.telephone AS parent_telephone,
                co.nom AS comptable_nom,
                r.id_recu, r.numero AS recu_numero, r.code_qr, r.fichier_pdf, r.date_emission
         FROM paiement pa
         JOIN frais f ON f.id_frais = pa.id_frais
         JOIN categorie_frais c ON c.id_categorie = f.id_categorie
         JOIN eleve e ON e.id_eleve = f.id_eleve
         JOIN classe cl ON cl.id_classe = e.id_classe
         JOIN utilisateur p ON p.id_utilisateur = e.id_parent
         LEFT JOIN utilisateur co ON co.id_utilisateur = pa.id_comptable
         LEFT JOIN recu r ON r.id_paiement = pa.id_paiement';

    /**
     * Référence unique : PAY-AAAAMMJJ-XXXXXX (6 caractères aléatoires, lettres majuscules et chiffres).
     * L'unicité est vérifiée en base ; la contrainte UNIQUE reste le garde-fou final.
     */
    public static function genererReference(?DateTimeInterface $date = null): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $suffixe = '';
            for ($i = 0; $i < 6; $i++) {
                $suffixe .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $reference = 'PAY-' . ($date ?? new DateTimeImmutable())->format('Ymd') . '-' . $suffixe;
        } while (self::trouverParReference($reference) !== null);
        return $reference;
    }

    public static function creer(array $d): int
    {
        $requete = Database::get()->prepare(
            'INSERT INTO paiement (reference, montant, mode, statut, date_paiement, id_frais, id_comptable)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $requete->execute([
            $d['reference'],
            number_format((float) $d['montant'], 2, '.', ''),
            $d['mode'],
            $d['statut'],
            $d['date_paiement'] ?? date('Y-m-d H:i:s'),
            $d['id_frais'],
            $d['id_comptable'] ?? null,
        ]);
        return (int) Database::get()->lastInsertId();
    }

    public static function trouver(int $id): ?array
    {
        $requete = Database::get()->prepare(self::SELECT_DETAIL . ' WHERE pa.id_paiement = ?');
        $requete->execute([$id]);
        return $requete->fetch() ?: null;
    }

    public static function trouverParReference(string $reference): ?array
    {
        $requete = Database::get()->prepare(self::SELECT_DETAIL . ' WHERE pa.reference = ?');
        $requete->execute([$reference]);
        return $requete->fetch() ?: null;
    }

    /** Paiements d'un frais, du plus récent au plus ancien. */
    public static function parFrais(int $idFrais): array
    {
        $requete = Database::get()->prepare(self::SELECT_DETAIL . ' WHERE pa.id_frais = ? ORDER BY pa.date_paiement DESC, pa.id_paiement DESC');
        $requete->execute([$idFrais]);
        return $requete->fetchAll();
    }

    /** Paiements des enfants d'un parent (historique de l'espace parent). */
    public static function parParent(int $idParent, int $limite = 100): array
    {
        $requete = Database::get()->prepare(self::SELECT_DETAIL . ' WHERE e.id_parent = ? ORDER BY pa.date_paiement DESC, pa.id_paiement DESC LIMIT ?');
        $requete->bindValue(1, $idParent, PDO::PARAM_INT);
        $requete->bindValue(2, $limite, PDO::PARAM_INT);
        $requete->execute();
        return $requete->fetchAll();
    }

    /**
     * Liste filtrée et paginée (comptable).
     * @param array{q?:string, statut?:string, mode?:string, du?:string, au?:string} $filtres
     * @return array{lignes: list<array>, total: int, somme_reussie: string}
     */
    public static function lister(array $filtres, int $page = 1, int $parPage = 20): array
    {
        $conditions = [];
        $parametres = [];
        if (($filtres['q'] ?? '') !== '') {
            $conditions[] = "(pa.reference LIKE ? OR e.matricule LIKE ? OR CONCAT(e.prenom, ' ', e.nom) LIKE ? OR CONCAT(e.nom, ' ', e.prenom) LIKE ? OR r.numero LIKE ?)";
            array_push($parametres, ...array_fill(0, 5, motifLike($filtres['q'])));
        }
        if (isset(self::STATUTS[$filtres['statut'] ?? ''])) {
            $conditions[] = 'pa.statut = ?';
            $parametres[] = $filtres['statut'];
        }
        if (isset(self::MODES[$filtres['mode'] ?? ''])) {
            $conditions[] = 'pa.mode = ?';
            $parametres[] = $filtres['mode'];
        }
        if (($filtres['du'] ?? '') !== '') {
            $conditions[] = 'pa.date_paiement >= ?';
            $parametres[] = $filtres['du'] . ' 00:00:00';
        }
        if (($filtres['au'] ?? '') !== '') {
            $conditions[] = 'pa.date_paiement <= ?';
            $parametres[] = $filtres['au'] . ' 23:59:59';
        }
        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $db = Database::get();

        $agregat = $db->prepare(
            "SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN pa.statut = 'reussi' THEN pa.montant END), 0) AS somme
             FROM paiement pa JOIN frais f ON f.id_frais = pa.id_frais JOIN eleve e ON e.id_eleve = f.id_eleve
             LEFT JOIN recu r ON r.id_paiement = pa.id_paiement" . $where
        );
        $agregat->execute($parametres);
        $a = $agregat->fetch();

        $requete = $db->prepare(self::SELECT_DETAIL . $where . ' ORDER BY pa.date_paiement DESC, pa.id_paiement DESC LIMIT ? OFFSET ?');
        $i = 1;
        foreach ($parametres as $valeur) {
            $requete->bindValue($i++, $valeur);
        }
        $requete->bindValue($i++, $parPage, PDO::PARAM_INT);
        $requete->bindValue($i, ($page - 1) * $parPage, PDO::PARAM_INT);
        $requete->execute();

        return ['lignes' => $requete->fetchAll(), 'total' => (int) $a['n'], 'somme_reussie' => $a['somme']];
    }

    public static function recents(int $nombre = 6): array
    {
        $requete = Database::get()->prepare(self::SELECT_DETAIL . ' ORDER BY pa.date_paiement DESC, pa.id_paiement DESC LIMIT ?');
        $requete->bindValue(1, $nombre, PDO::PARAM_INT);
        $requete->execute();
        return $requete->fetchAll();
    }

    /** Encaissements réussis du jour : nombre et somme. */
    public static function encaissementsDuJour(): array
    {
        $requete = Database::get()->prepare(
            "SELECT COUNT(*) AS n, COALESCE(SUM(montant), 0) AS somme FROM paiement
             WHERE statut = 'reussi' AND DATE(date_paiement) = CURDATE()"
        );
        $requete->execute();
        return $requete->fetch();
    }

    // --- Rapports (seuls les paiements réussis comptent) -------------------------------

    /**
     * Totaux d'une période : montant et nombre de paiements réussis, dont en ligne.
     * @return array{somme:string, nb:int, somme_en_ligne:string, nb_en_ligne:int, echoues:int}
     */
    public static function totauxPeriode(string $du, string $au): array
    {
        $requete = Database::get()->prepare(
            "SELECT COALESCE(SUM(CASE WHEN statut = 'reussi' THEN montant END), 0) AS somme,
                    SUM(statut = 'reussi') AS nb,
                    COALESCE(SUM(CASE WHEN statut = 'reussi' AND mode <> 'especes' THEN montant END), 0) AS somme_en_ligne,
                    SUM(statut = 'reussi' AND mode <> 'especes') AS nb_en_ligne,
                    SUM(statut = 'echoue') AS echoues
             FROM paiement
             WHERE date_paiement BETWEEN ? AND ?"
        );
        $requete->execute([$du . ' 00:00:00', $au . ' 23:59:59']);
        $t = $requete->fetch();
        return [
            'somme' => $t['somme'], 'nb' => (int) $t['nb'],
            'somme_en_ligne' => $t['somme_en_ligne'], 'nb_en_ligne' => (int) $t['nb_en_ligne'],
            'echoues' => (int) $t['echoues'],
        ];
    }

    /**
     * Ventilation des encaissements d'une période par catégorie, classe ou mode.
     * @return list<array{libelle:string, nb:int, somme:string}>
     */
    public static function ventilation(string $du, string $au, string $dimension): array
    {
        $colonne = match ($dimension) {
            'categorie' => 'c.libelle',
            'classe'    => 'cl.libelle',
            'mode'      => 'pa.mode',
            default     => throw new InvalidArgumentException("Dimension inconnue : $dimension"),
        };
        $requete = Database::get()->prepare(
            "SELECT $colonne AS libelle, COUNT(*) AS nb, SUM(pa.montant) AS somme
             FROM paiement pa
             JOIN frais f ON f.id_frais = pa.id_frais
             JOIN categorie_frais c ON c.id_categorie = f.id_categorie
             JOIN eleve e ON e.id_eleve = f.id_eleve
             JOIN classe cl ON cl.id_classe = e.id_classe
             WHERE pa.statut = 'reussi' AND pa.date_paiement BETWEEN ? AND ?
             GROUP BY $colonne
             ORDER BY somme DESC"
        );
        $requete->execute([$du . ' 00:00:00', $au . ' 23:59:59']);
        $lignes = $requete->fetchAll();
        if ($dimension === 'mode') {
            foreach ($lignes as &$ligne) {
                $ligne['libelle'] = self::MODES[$ligne['libelle']];
            }
        }
        return $lignes;
    }

    /**
     * Série chronologique des encaissements, par jour ou par mois, sans trou :
     * les périodes sans paiement valent 0.
     * @return list<array{cle:string, somme:float, nb:int}>
     */
    public static function serie(string $du, string $au, string $pas): array
    {
        $format = $pas === 'mois' ? '%Y-%m' : '%Y-%m-%d';
        $requete = Database::get()->prepare(
            "SELECT DATE_FORMAT(date_paiement, '$format') AS cle, SUM(montant) AS somme, COUNT(*) AS nb
             FROM paiement
             WHERE statut = 'reussi' AND date_paiement BETWEEN ? AND ?
             GROUP BY cle"
        );
        $requete->execute([$du . ' 00:00:00', $au . ' 23:59:59']);
        $valeurs = [];
        foreach ($requete->fetchAll() as $ligne) {
            $valeurs[$ligne['cle']] = $ligne;
        }

        $serie = [];
        $curseur = new DateTimeImmutable($pas === 'mois' ? substr($du, 0, 7) . '-01' : $du);
        $fin = new DateTimeImmutable($au);
        while ($curseur <= $fin) {
            $cle = $curseur->format($pas === 'mois' ? 'Y-m' : 'Y-m-d');
            $serie[] = ['cle' => $cle, 'somme' => (float) ($valeurs[$cle]['somme'] ?? 0), 'nb' => (int) ($valeurs[$cle]['nb'] ?? 0)];
            $curseur = $curseur->modify($pas === 'mois' ? '+1 month' : '+1 day');
        }
        return $serie;
    }

    /** Paiement en ligne encore en attente pour un frais (un seul à la fois), ou null. */
    public static function enAttentePourFrais(int $idFrais): ?array
    {
        $requete = Database::get()->prepare(
            self::SELECT_DETAIL . " WHERE pa.id_frais = ? AND pa.statut = 'en_attente' ORDER BY pa.id_paiement DESC LIMIT 1"
        );
        $requete->execute([$idFrais]);
        return $requete->fetch() ?: null;
    }

    /**
     * Les paiements en ligne restés sans réponse de la passerelle au-delà du délai
     * sont considérés comme abandonnés (échoués) : le parent peut alors réessayer.
     * @return int nombre de paiements expirés
     */
    public static function expirerEnAttente(int $idFrais, int $minutes = 30): int
    {
        $requete = Database::get()->prepare(
            "UPDATE paiement SET statut = 'echoue'
             WHERE id_frais = ? AND statut = 'en_attente' AND date_paiement < NOW() - INTERVAL ? MINUTE"
        );
        $requete->execute([$idFrais, $minutes]);
        return $requete->rowCount();
    }

    /** Passe un paiement à un nouveau statut (utilisé par le callback de la passerelle). */
    public static function changerStatut(int $id, string $statut): void
    {
        $requete = Database::get()->prepare('UPDATE paiement SET statut = ? WHERE id_paiement = ?');
        $requete->execute([$statut, $id]);
    }
}
