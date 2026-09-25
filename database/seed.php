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

// Comptes de démonstration (connexion possible) puis familles supplémentaires.
// Clé : identifiant logique réutilisé pour rattacher les élèves.
$utilisateurs = [
    'admin'      => ['Grâce Mwamba',        'admin@oliviers.cd',        'admin',     '+243 97 100 0001', 'Avenue de l\'Université, Kolwezi'],
    'comptable'  => ['Patrick Ilunga',      'comptable@oliviers.cd',    'comptable', '+243 97 100 0002', 'Quartier Latin, Kolwezi'],
    'kabongo'    => ['Jean-Pierre Kabongo', 'parent@oliviers.cd',       'parent',    '+243 97 200 0001', 'Avenue Kasavubu 12, Manika, Kolwezi'],
    'mujinga'    => ['Esther Mujinga',      'e.mujinga@oliviers.cd',    'parent',    '+243 81 200 0002', 'Avenue Lumumba 45, Dilala, Kolwezi'],
    'tshibangu'  => ['Didier Tshibangu',    'd.tshibangu@oliviers.cd',  'parent',    '+243 99 200 0003', 'Quartier Mutoshi, Kolwezi'],
    'kasongo'    => ['Chantal Kasongo',     'c.kasongo@oliviers.cd',    'parent',    '+243 85 200 0004', 'Cité Gécamines, Kolwezi'],
    'mbuyi'      => ['Albert Mbuyi',        'albert.mbuyi@exemple.cd',  'parent',    '+243 97 311 2045', 'Avenue Mwilu 8, Manika, Kolwezi'],
    'ngoy'       => ['Béatrice Ngoy',       'beatrice.ngoy@exemple.cd', 'parent',    '+243 81 422 7310', 'Quartier Kasulo, Kolwezi'],
    'kalala'     => ['Célestin Kalala',     'c.kalala@exemple.cd',      'parent',    '+243 99 530 1188', 'Avenue du Lac 21, Dilala, Kolwezi'],
    'kyungu'     => ['Aimée Kyungu',        'aimee.kyungu@exemple.cd',  'parent',    '+243 85 614 9072', 'Cité Musonoie, Kolwezi'],
    'banza'      => ['Freddy Banza',        'freddy.banza@exemple.cd',  'parent',    '+243 97 702 3364', 'Avenue Mzee 3, Manika, Kolwezi'],
    'mukendi'    => ['Nadine Mukendi',      'n.mukendi@exemple.cd',     'parent',    '+243 81 845 5521', 'Quartier Joli Site, Kolwezi'],
    'kapinga'    => ['Gustave Kapinga',     'g.kapinga@exemple.cd',     'parent',    '+243 99 918 6603', 'Avenue Kamanyola 17, Kolwezi'],
    'lukusa'     => ['Solange Lukusa',      'solange.lukusa@exemple.cd', 'parent',   '+243 85 127 4480', 'Cité Gécamines, Kolwezi'],
    'mutombo'    => ['Hervé Mutombo',       'h.mutombo@exemple.cd',     'parent',    '+243 97 236 8815', 'Quartier Mutoshi, Kolwezi'],
    'kayembe'    => ['Clarisse Kayembe',    'c.kayembe@exemple.cd',     'parent',    '+243 81 347 0926', 'Avenue de la Paix 40, Dilala, Kolwezi'],
    'numbi'      => ['Papy Numbi',          'papy.numbi@exemple.cd',    'parent',    '+243 99 458 2217', 'Quartier Kasulo, Kolwezi'],
    'kitenge'    => ['Odette Kitenge',      'o.kitenge@exemple.cd',     'parent',    '+243 85 569 7734', 'Avenue Mobutu 6, Manika, Kolwezi'],
];
$insertion = $db->prepare(
    'INSERT INTO utilisateur (nom, email, mot_de_passe, role, telephone, adresse, date_creation)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$ids = [];
foreach ($utilisateurs as $cle => [$nom, $email, $role, $telephone, $adresse]) {
    // Comptes du personnel créés avant la rentrée, parents au moment des inscriptions.
    $creation = in_array($role, ['admin', 'comptable'], true) ? '2026-07-01 08:00:00' : '2026-08-17 09:00:00';
    $insertion->execute([$nom, $email, $motDePasse, $role, $telephone, $adresse, $creation]);
    $ids[$cle] = (int) $db->lastInsertId();
}
echo count($utilisateurs) . " utilisateurs créés (mot de passe : Demo@2026).\n";

// Classes : de la 1re à la 6e, sections variées.
$classes = [
    '1re' => ['1re', 'Orientation',  '1re Orientation'],
    '2e'  => ['2e',  'Orientation',  '2e Orientation'],
    '3e'  => ['3e',  'Scientifique', '3e Scientifique'],
    '4e'  => ['4e',  'Commerciale',  '4e Commerciale'],
    '5e'  => ['5e',  'Latin-Philo',  '5e Latin-Philo'],
    '6e'  => ['6e',  'Scientifique', '6e Scientifique'],
];
$idsClasses = [];
$insertion = $db->prepare('INSERT INTO classe (niveau, section, libelle) VALUES (?, ?, ?)');
foreach ($classes as $cle => $classe) {
    $insertion->execute($classe);
    $idsClasses[$cle] = (int) $db->lastInsertId();
}
echo count($classes) . " classes créées.\n";

// 24 élèves (4 par classe). Le parent de démonstration (parent@oliviers.cd) a 2 enfants.
$eleves = [
    // [nom, prénom, classe, parent, date d'inscription]
    ['Kabongo',     'Merveille',  '3e',  'kabongo',   '2026-08-18'],
    ['Kabongo',     'Glody',      '5e',  'kabongo',   '2026-08-18'],
    ['Kazadi',      'Ruth',       '1re', 'mujinga',   '2026-08-19'],
    ['Kazadi',      'Daniel',     '4e',  'mujinga',   '2026-08-19'],
    ['Tshibangu',   'Exaucé',     '6e',  'tshibangu', '2026-08-20'],
    ['Tshibangu',   'Divine',     '2e',  'tshibangu', '2026-08-20'],
    ['Mpoyi',       'Béni',       '1re', 'kasongo',   '2026-08-21'],
    ['Mpoyi',       'Sarah',      '3e',  'kasongo',   '2026-08-21'],
    ['Mbuyi',       'Jonathan',   '4e',  'mbuyi',     '2026-08-24'],
    ['Mbuyi',       'Christelle', '6e',  'mbuyi',     '2026-08-24'],
    ['Ngoy',        'Plamedi',    '1re', 'ngoy',      '2026-08-25'],
    ['Kalala',      'Josué',      '2e',  'kalala',    '2026-08-26'],
    ['Kalala',      'Rachel',     '5e',  'kalala',    '2026-08-26'],
    ['Kyungu',      'Gloire',     '3e',  'kyungu',    '2026-08-27'],
    ['Banza',       'Emmanuel',   '4e',  'banza',     '2026-08-28'],
    ['Banza',       'Nathalie',   '6e',  'banza',     '2026-08-28'],
    ['Mukendi',     'Chancelle',  '2e',  'mukendi',   '2026-08-31'],
    ['Kapinga',     'Fiston',     '5e',  'kapinga',   '2026-09-01'],
    ['Lukusa',      'Joël',       '1re', 'lukusa',    '2026-09-01'],
    ['Lukusa',      'Bénédicte',  '4e',  'lukusa',    '2026-09-01'],
    ['Mutombo',     'Patient',    '6e',  'mutombo',   '2026-09-02'],
    ['Kayembe',     'Espoir',     '3e',  'kayembe',   '2026-09-03'],
    ['Numbi',       'Dieudonné',  '5e',  'numbi',     '2026-09-04'],
    ['Kitenge',     'Grâce',      '2e',  'kitenge',   '2026-09-07'],
];
$insertion = $db->prepare(
    'INSERT INTO eleve (matricule, nom, prenom, date_inscription, id_classe, id_parent) VALUES (?, ?, ?, ?, ?, ?)'
);
foreach ($eleves as $i => [$nom, $prenom, $classe, $parent, $date]) {
    $matricule = sprintf('IO-2026-%03d', $i + 1);
    $insertion->execute([$matricule, $nom, $prenom, $date, $idsClasses[$classe], $ids[$parent]]);
}
echo count($eleves) . " élèves inscrits.\n";
echo "Terminé.\n";
