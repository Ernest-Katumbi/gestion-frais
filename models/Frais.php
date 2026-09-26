<?php
declare(strict_types=1);

/**
 * Modèle « frais » : montant dû par un élève pour une catégorie et une échéance.
 *
 * Invariants garantis (application + contraintes CHECK de la base) :
 *   0 <= montant_paye <= montant
 *   statut = 'impaye' si montant_paye = 0, 'partiel' si 0 < montant_paye < montant, 'paye' si montant_paye = montant
 */
final class Frais
{
    public const STATUTS = ['impaye' => 'Impayé', 'partiel' => 'Partiel', 'paye' => 'Payé'];

    private const SELECT_DETAIL =
        'SELECT f.*, (f.montant - f.montant_paye) AS reste,
                c.code AS categorie_code, c.libelle AS categorie, c.periodicite,
                e.id_eleve, e.matricule, e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.id_parent,
                cl.id_classe, cl.libelle AS classe
         FROM frais f
         JOIN categorie_frais c ON c.id_categorie = f.id_categorie
         JOIN eleve e ON e.id_eleve = f.id_eleve
         JOIN classe cl ON cl.id_classe = e.id_classe';

    // --- Règles métier ------------------------------------------------------------

    /**
     * Statut d'un frais après un versement (fonction pure, sans accès à la base).
     * Les calculs sont faits en centimes pour éviter les erreurs d'arrondi des flottants.
     *
     * @throws DomainException si le versement est nul ou négatif, ou s'il dépasse le reste à payer
     */
    public static function statutApres(float $montant, float $dejaPaye, float $verse): string
    {
        $montantCts = (int) round($montant * 100);
        $dejaPayeCts = (int) round($dejaPaye * 100);
        $verseCts = (int) round($verse * 100);

        if ($verseCts <= 0) {
            throw new DomainException('Le montant versé doit être supérieur à zéro.');
        }
        if ($dejaPayeCts + $verseCts > $montantCts) {
            throw new DomainException(sprintf(
                'Le montant versé (%s) dépasse le reste à payer (%s).',
                formaterMontant($verse),
                formaterMontant(($montantCts - $dejaPayeCts) / 100)
            ));
        }

        $total = $dejaPayeCts + $verseCts;
        return match (true) {
            $total === 0          => 'impaye',
            $total < $montantCts  => 'partiel',
            default               => 'paye',
        };
    }

    /**
     * Enregistre un versement sur un frais de façon atomique : montant payé et statut
     * sont mis à jour par une seule requête, qui ne modifie la ligne que si le versement
     * ne dépasse pas le reste à payer (protection contre les encaissements simultanés).
     *
     * @throws DomainException si aucune ligne n'est modifiée (dépassement ou frais inexistant)
     */
    public static function enregistrerVersement(int $idFrais, float $montant): void
    {
        $verse = number_format($montant, 2, '.', '');
        // Le statut est calculé en premier, à partir de l'ancien montant payé :
        // le résultat est identique quel que soit l'ordre d'évaluation des affectations.
        $requete = Database::get()->prepare(
            "UPDATE frais
             SET statut = CASE
                     WHEN montant_paye + CAST(? AS DECIMAL(10,2)) >= montant THEN 'paye'
                     WHEN montant_paye + CAST(? AS DECIMAL(10,2)) > 0 THEN 'partiel'
                     ELSE 'impaye'
                 END,
                 montant_paye = montant_paye + CAST(? AS DECIMAL(10,2))
             WHERE id_frais = ?
               AND CAST(? AS DECIMAL(10,2)) > 0
               AND montant_paye + CAST(? AS DECIMAL(10,2)) <= montant"
        );
        $requete->execute([$verse, $verse, $verse, $idFrais, $verse, $verse]);

        if ($requete->rowCount() === 0) {
            throw new DomainException('Versement refusé : le montant dépasse le reste à payer de ce frais.');
        }
    }

    /** Montant minimal d'un versement : 1,00 (ou le reste s'il est inférieur). */
    public static function versementMinimal(float $reste): float
    {
        return min(1.00, round($reste, 2));
    }

    // --- Lecture ------------------------------------------------------------------

    /** Frais avec sa catégorie, son élève (dont id_parent) et sa classe. */
    public static function trouver(int $id): ?array
    {
        $requete = Database::get()->prepare(self::SELECT_DETAIL . ' WHERE f.id_frais = ?');
        $requete->execute([$id]);
        return $requete->fetch() ?: null;
    }

    /** Verrouille la ligne du frais jusqu'à la fin de la transaction (SELECT … FOR UPDATE). */
    public static function verrouiller(int $id): ?array
    {
        $requete = Database::get()->prepare(self::SELECT_DETAIL . ' WHERE f.id_frais = ? FOR UPDATE');
        $requete->execute([$id]);
        return $requete->fetch() ?: null;
    }

    /** Tous les frais d'un élève, par échéance. */
    public static function parEleve(int $idEleve, bool $nonSoldesSeulement = false): array
    {
        $requete = Database::get()->prepare(
            self::SELECT_DETAIL . ' WHERE f.id_eleve = ?' . ($nonSoldesSeulement ? " AND f.statut <> 'paye'" : '')
            . ' ORDER BY f.echeance, c.libelle'
        );
        $requete->execute([$idEleve]);
        return $requete->fetchAll();
    }

    /** Totaux dû / payé / reste pour une liste de frais. */
    public static function totaux(array $frais): array
    {
        $du = $paye = 0;
        foreach ($frais as $f) {
            $du += (int) round((float) $f['montant'] * 100);
            $paye += (int) round((float) $f['montant_paye'] * 100);
        }
        return ['du' => $du / 100, 'paye' => $paye / 100, 'reste' => ($du - $paye) / 100];
    }

    /**
     * Impayés (statut impayé ou partiel) filtrés par classe, catégorie et échéance.
     * @param array{classe?:int, categorie?:int, echeance_max?:string, echus?:bool} $filtres
     * @return array{lignes: list<array>, total: int, totaux: array}
     */
    public static function impayes(array $filtres, int $page = 1, int $parPage = 20): array
    {
        [$where, $parametres] = self::conditionsImpayes($filtres);
        $db = Database::get();

        $agregat = $db->prepare(
            'SELECT COUNT(*) AS n, COALESCE(SUM(f.montant), 0) AS du, COALESCE(SUM(f.montant_paye), 0) AS paye,
                    COALESCE(SUM(f.montant - f.montant_paye), 0) AS reste, COUNT(DISTINCT f.id_eleve) AS eleves
             FROM frais f JOIN eleve e ON e.id_eleve = f.id_eleve' . $where
        );
        $agregat->execute($parametres);
        $totaux = $agregat->fetch();

        $requete = $db->prepare(
            str_replace('SELECT f.*,', 'SELECT f.*, p.nom AS parent_nom, p.telephone AS parent_telephone,', self::SELECT_DETAIL)
            . ' JOIN utilisateur p ON p.id_utilisateur = e.id_parent'
            . $where . ' ORDER BY f.echeance, cl.libelle, e.nom, e.prenom LIMIT ? OFFSET ?'
        );
        $i = 1;
        foreach ($parametres as $valeur) {
            $requete->bindValue($i++, $valeur, is_int($valeur) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $requete->bindValue($i++, $parPage, PDO::PARAM_INT);
        $requete->bindValue($i, ($page - 1) * $parPage, PDO::PARAM_INT);
        $requete->execute();

        return ['lignes' => $requete->fetchAll(), 'total' => (int) $totaux['n'], 'totaux' => $totaux];
    }

    /** Toutes les lignes d'impayés (sans pagination) pour l'export. */
    public static function tousImpayes(array $filtres): array
    {
        return self::impayes($filtres, 1, 100000)['lignes'];
    }

    /** @return array{0: string, 1: list<int|string>} */
    private static function conditionsImpayes(array $filtres): array
    {
        $conditions = ["f.statut <> 'paye'"];
        $parametres = [];
        if (!empty($filtres['classe'])) {
            $conditions[] = 'e.id_classe = ?';
            $parametres[] = (int) $filtres['classe'];
        }
        if (!empty($filtres['categorie'])) {
            $conditions[] = 'f.id_categorie = ?';
            $parametres[] = (int) $filtres['categorie'];
        }
        if (!empty($filtres['echeance_max'])) {
            $conditions[] = 'f.echeance <= ?';
            $parametres[] = $filtres['echeance_max'];
        }
        if (!empty($filtres['echus'])) {
            $conditions[] = 'f.echeance < CURDATE()';
        }
        return [' WHERE ' . implode(' AND ', $conditions), $parametres];
    }

    /**
     * Récapitulatif des affectations : une ligne par (catégorie, classe, échéance).
     */
    public static function affectations(): array
    {
        $requete = Database::get()->prepare(
            "SELECT f.id_categorie, c.libelle AS categorie, c.code, cl.id_classe, cl.libelle AS classe, f.echeance,
                    COUNT(*) AS nb_eleves, SUM(f.montant) AS du, SUM(f.montant_paye) AS paye,
                    SUM(f.statut = 'paye') AS nb_payes,
                    (SELECT COUNT(*) FROM paiement pa JOIN frais f2 ON f2.id_frais = pa.id_frais JOIN eleve e2 ON e2.id_eleve = f2.id_eleve
                      WHERE f2.id_categorie = f.id_categorie AND e2.id_classe = cl.id_classe AND f2.echeance = f.echeance) AS nb_paiements
             FROM frais f
             JOIN categorie_frais c ON c.id_categorie = f.id_categorie
             JOIN eleve e ON e.id_eleve = f.id_eleve
             JOIN classe cl ON cl.id_classe = e.id_classe
             GROUP BY f.id_categorie, cl.id_classe, f.echeance
             ORDER BY f.echeance, c.libelle, CAST(cl.niveau AS UNSIGNED), cl.libelle"
        );
        $requete->execute();
        return $requete->fetchAll();
    }

    /**
     * Annule une affectation (catégorie, classe, échéance) si aucun paiement,
     * même échoué, n'a été enregistré sur ces frais. Renvoie le nombre de frais supprimés.
     */
    public static function annulerAffectation(int $idCategorie, int $idClasse, string $echeance): int
    {
        $db = Database::get();
        $db->beginTransaction();
        try {
            $verif = $db->prepare(
                'SELECT COUNT(*) FROM paiement pa JOIN frais f ON f.id_frais = pa.id_frais JOIN eleve e ON e.id_eleve = f.id_eleve
                 WHERE f.id_categorie = ? AND e.id_classe = ? AND f.echeance = ?'
            );
            $verif->execute([$idCategorie, $idClasse, $echeance]);
            if ((int) $verif->fetchColumn() > 0) {
                throw new DomainException('Des paiements ont déjà été enregistrés sur ces frais : l\'affectation ne peut plus être annulée.');
            }
            $suppression = $db->prepare(
                'DELETE f FROM frais f JOIN eleve e ON e.id_eleve = f.id_eleve
                 WHERE f.id_categorie = ? AND e.id_classe = ? AND f.echeance = ? AND f.montant_paye = 0'
            );
            $suppression->execute([$idCategorie, $idClasse, $echeance]);
            $db->commit();
            return $suppression->rowCount();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /** Indicateurs globaux : dû, encaissé, reste, nombre de frais échus non soldés. */
    public static function indicateurs(): array
    {
        $requete = Database::get()->prepare(
            "SELECT COALESCE(SUM(montant), 0) AS du, COALESCE(SUM(montant_paye), 0) AS paye,
                    COALESCE(SUM(montant - montant_paye), 0) AS reste,
                    SUM(statut <> 'paye' AND echeance < CURDATE()) AS echus,
                    COALESCE(SUM(CASE WHEN statut <> 'paye' AND echeance < CURDATE() THEN montant - montant_paye END), 0) AS reste_echu
             FROM frais"
        );
        $requete->execute();
        return $requete->fetch();
    }

    /**
     * Frais non soldés dont l'échéance tombe entre $jour et $jour + $jours (inclus),
     * avec les coordonnées du parent : utilisé par cron/rappels_echeances.php.
     */
    public static function aRappeler(string $jour, int $jours = 3): array
    {
        $requete = Database::get()->prepare(
            str_replace('SELECT f.*,', 'SELECT f.*, p.nom AS parent_nom, p.email AS parent_email,', self::SELECT_DETAIL)
            . " JOIN utilisateur p ON p.id_utilisateur = e.id_parent
               WHERE f.statut <> 'paye' AND p.statut = 'actif'
                 AND f.echeance BETWEEN ? AND ? + INTERVAL ? DAY
               ORDER BY f.echeance, e.nom, e.prenom"
        );
        $requete->execute([$jour, $jour, $jours]);
        return $requete->fetchAll();
    }

    /** Reste à recouvrer par classe (rapport). */
    public static function impayesParClasse(): array
    {
        $requete = Database::get()->prepare(
            "SELECT cl.libelle, COUNT(*) AS nb, SUM(f.montant - f.montant_paye) AS reste, COUNT(DISTINCT f.id_eleve) AS eleves
             FROM frais f JOIN eleve e ON e.id_eleve = f.id_eleve JOIN classe cl ON cl.id_classe = e.id_classe
             WHERE f.statut <> 'paye'
             GROUP BY cl.id_classe ORDER BY CAST(cl.niveau AS UNSIGNED), cl.libelle"
        );
        $requete->execute();
        return $requete->fetchAll();
    }

    /** Frais non soldés dont l'échéance tombe dans les N prochains jours. */
    public static function echeancesProches(int $jours = 7): array
    {
        $requete = Database::get()->prepare(
            "SELECT c.libelle AS categorie, f.echeance, COUNT(*) AS nb, SUM(f.montant - f.montant_paye) AS reste
             FROM frais f JOIN categorie_frais c ON c.id_categorie = f.id_categorie
             WHERE f.statut <> 'paye' AND f.echeance BETWEEN CURDATE() AND CURDATE() + INTERVAL ? DAY
             GROUP BY f.id_categorie, f.echeance ORDER BY f.echeance"
        );
        $requete->execute([$jours]);
        return $requete->fetchAll();
    }
}
