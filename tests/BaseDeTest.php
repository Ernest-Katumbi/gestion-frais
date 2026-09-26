<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Classe de base des tests : recrée la base de test (schéma complet) avant chaque test
 * et fournit un jeu de données minimal (un parent, un comptable, une classe, un élève,
 * une catégorie à 150,00 et un frais « impayé »).
 */
abstract class BaseDeTest extends TestCase
{
    protected PDO $db;
    protected array $ids = [];

    protected function setUp(): void
    {
        Database::serveur()->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        Database::definir(null);
        $this->db = Database::get();
        $sql = (string) preg_replace('/^\s*--.*$/m', '', (string) file_get_contents(RACINE . '/database/gestion_frais.sql'));
        foreach (preg_split('/;\s*$/m', $sql) as $instruction) {
            if (trim($instruction) !== '') {
                $this->db->exec($instruction);
            }
        }
        $this->creerJeuDeDonnees();
    }

    private function creerJeuDeDonnees(): void
    {
        $this->ids['parent'] = User::creer([
            'nom' => 'Parent Test', 'email' => 'parent.test@exemple.cd', 'role' => 'parent', 'mot_de_passe' => 'Test1234',
        ]);
        $this->ids['comptable'] = User::creer([
            'nom' => 'Comptable Test', 'email' => 'comptable.test@exemple.cd', 'role' => 'comptable', 'mot_de_passe' => 'Test1234',
        ]);
        $this->ids['classe'] = Classe::creer(['niveau' => '3e', 'section' => 'Scientifique', 'libelle' => '3e Scientifique']);
        $this->ids['eleve'] = Eleve::creer([
            'matricule' => 'IO-2026-001', 'nom' => 'Kabongo', 'prenom' => 'Merveille', 'date_inscription' => date('Y-m-d'),
            'id_classe' => $this->ids['classe'], 'id_parent' => $this->ids['parent'],
        ]);
        $this->ids['categorie'] = CategorieFrais::creer([
            'code' => 'MIN-T1', 'libelle' => 'Minerval 1er trimestre', 'montant_defaut' => '150.00', 'periodicite' => 'trimestrielle',
        ]);
        (new FraisController())->assigner($this->ids['categorie'], $this->ids['classe'], date('Y-m-d', strtotime('+10 days')));
        $this->ids['frais'] = (int) $this->valeur('SELECT id_frais FROM frais LIMIT 1');
    }

    /** Première colonne de la première ligne d'une requête. */
    protected function valeur(string $sql, array $parametres = []): mixed
    {
        $requete = $this->db->prepare($sql);
        $requete->execute($parametres);
        return $requete->fetchColumn();
    }

    protected function frais(): array
    {
        return Frais::trouver($this->ids['frais']);
    }
}
