<?php
/**
 * Installation de la base et données de démonstration.
 *
 *   php database/seed.php
 *
 * Crée la base si nécessaire, (ré)importe le schéma gestion_frais.sql puis insère
 * les données de démonstration. ATTENTION : toutes les données existantes sont effacées.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/core/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit('Script à lancer en ligne de commande.');
}

// 1. Création de la base (connexion sans base sélectionnée).
$serveur = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT), DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$serveur->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', DB_NAME) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
echo "Base « " . DB_NAME . " » prête.\n";

// 2. Import du schéma (instructions séparées par « ; » en fin de ligne).
$db = Database::get();
$sql = (string) file_get_contents(__DIR__ . '/gestion_frais.sql');
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
foreach (preg_split('/;\s*$/m', $sql) as $instruction) {
    if (trim($instruction) !== '') {
        $db->exec($instruction);
    }
}
echo "Schéma importé.\n";

// 3. Données de démonstration.
$motDePasse = password_hash('Demo@2026', PASSWORD_DEFAULT);

$utilisateurs = [
    ['Grâce Mwamba',        'admin@oliviers.cd',     'admin',     '+243 97 100 0001', 'Avenue de l\'Université, Kolwezi'],
    ['Patrick Ilunga',      'comptable@oliviers.cd', 'comptable', '+243 97 100 0002', 'Quartier Latin, Kolwezi'],
    ['Jean-Pierre Kabongo', 'parent@oliviers.cd',    'parent',    '+243 97 200 0001', 'Avenue Kasavubu 12, Manika, Kolwezi'],
    ['Esther Mujinga',      'e.mujinga@oliviers.cd', 'parent',    '+243 81 200 0002', 'Avenue Lumumba 45, Dilala, Kolwezi'],
    ['Didier Tshibangu',    'd.tshibangu@oliviers.cd', 'parent',  '+243 99 200 0003', 'Quartier Mutoshi, Kolwezi'],
    ['Chantal Kasongo',     'c.kasongo@oliviers.cd', 'parent',    '+243 85 200 0004', 'Cité Gécamines, Kolwezi'],
];
$insertion = $db->prepare(
    'INSERT INTO utilisateur (nom, email, mot_de_passe, role, telephone, adresse) VALUES (?, ?, ?, ?, ?, ?)'
);
foreach ($utilisateurs as [$nom, $email, $role, $telephone, $adresse]) {
    $insertion->execute([$nom, $email, $motDePasse, $role, $telephone, $adresse]);
}
echo count($utilisateurs) . " utilisateurs créés (mot de passe : Demo@2026).\n";
echo "Terminé.\n";
