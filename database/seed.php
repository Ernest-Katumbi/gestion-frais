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
// Toutes les dates sont relatives au jour de l'installation : la démonstration présente
// toujours des échéances passées, proches et futures, et un historique cohérent.
$aujourdhui = new DateTimeImmutable('today');
$jour = static fn(int $decalage): string => $aujourdhui->modify(sprintf('%+d days', $decalage))->format('Y-m-d');
$heure = static fn(int $decalage, int $h, int $m): string => $jour($decalage) . sprintf(' %02d:%02d:00', $h, $m);

// Anciens reçus PDF supprimés (la base est recréée).
foreach (glob(RACINE . '/recus/*.pdf') ?: [] as $pdf) {
    unlink($pdf);
}

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
    $creation = in_array($role, ['admin', 'comptable'], true) ? $heure(-86, 8, 0) : $heure(-39, 9, 0);
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
    // [nom, prénom, classe, parent, inscription (en jours par rapport à aujourd'hui)]
    ['Kabongo',     'Merveille',  '3e',  'kabongo',   -38],
    ['Kabongo',     'Glody',      '5e',  'kabongo',   -38],
    ['Kazadi',      'Ruth',       '1re', 'mujinga',   -37],
    ['Kazadi',      'Daniel',     '4e',  'mujinga',   -37],
    ['Tshibangu',   'Exaucé',     '6e',  'tshibangu', -36],
    ['Tshibangu',   'Divine',     '2e',  'tshibangu', -36],
    ['Mpoyi',       'Béni',       '1re', 'kasongo',   -35],
    ['Mpoyi',       'Sarah',      '3e',  'kasongo',   -35],
    ['Mbuyi',       'Jonathan',   '4e',  'mbuyi',     -32],
    ['Mbuyi',       'Christelle', '6e',  'mbuyi',     -32],
    ['Ngoy',        'Plamedi',    '1re', 'ngoy',      -31],
    ['Kalala',      'Josué',      '2e',  'kalala',    -30],
    ['Kalala',      'Rachel',     '5e',  'kalala',    -30],
    ['Kyungu',      'Gloire',     '3e',  'kyungu',    -29],
    ['Banza',       'Emmanuel',   '4e',  'banza',     -28],
    ['Banza',       'Nathalie',   '6e',  'banza',     -28],
    ['Mukendi',     'Chancelle',  '2e',  'mukendi',   -25],
    ['Kapinga',     'Fiston',     '5e',  'kapinga',   -24],
    ['Lukusa',      'Joël',       '1re', 'lukusa',    -24],
    ['Lukusa',      'Bénédicte',  '4e',  'lukusa',    -24],
    ['Mutombo',     'Patient',    '6e',  'mutombo',   -23],
    ['Kayembe',     'Espoir',     '3e',  'kayembe',   -22],
    ['Numbi',       'Dieudonné',  '5e',  'numbi',     -21],
    ['Kitenge',     'Grâce',      '2e',  'kitenge',   -18],
];
$insertion = $db->prepare(
    'INSERT INTO eleve (matricule, nom, prenom, date_inscription, id_classe, id_parent) VALUES (?, ?, ?, ?, ?, ?)'
);
$datesInscription = [];
foreach ($eleves as $i => [$nom, $prenom, $classe, $parent, $decalage]) {
    $date = $jour($decalage);
    $datesInscription[$i] = $decalage;
    $matricule = sprintf('IO-2026-%03d', $i + 1);
    $insertion->execute([$matricule, $nom, $prenom, $date, $idsClasses[$classe], $ids[$parent]]);
}
echo count($eleves) . " élèves inscrits.\n";

// Catégories de frais : échéances passées (inscription), proches (minerval T1, dans 3 jours) et futures.

$categories = [
    // clé => [code, libellé, montant, périodicité, échéance]
    'inscr' => ['INSCR',  'Frais d\'inscription',    50.00,  'unique',        $jour(-20)],
    'mint1' => ['MIN-T1', 'Minerval 1er trimestre',  150.00, 'trimestrielle', $jour(3)],
    'exam'  => ['EXAM',   'Frais d\'examen',         40.00,  'annuelle',      $jour(45)],
    'mint2' => ['MIN-T2', 'Minerval 2e trimestre',   150.00, 'trimestrielle', $jour(100)],
];
$insertion = $db->prepare('INSERT INTO categorie_frais (code, libelle, montant_defaut, periodicite) VALUES (?, ?, ?, ?)');
$idsCategories = [];
foreach ($categories as $cle => [$code, $libelle, $montant, $periodicite]) {
    $insertion->execute([$code, $libelle, $montant, $periodicite]);
    $idsCategories[$cle] = (int) $db->lastInsertId();
}

// Affectation à toutes les classes avec la méthode de l'application (FraisController::assigner).
$affectation = new FraisController();
$nbFrais = 0;
foreach ($categories as $cle => $categorie) {
    foreach ($idsClasses as $idClasse) {
        $nbFrais += $affectation->assigner($idsCategories[$cle], $idClasse, $categorie[4]);
    }
}
echo count($categories) . " catégories, $nbFrais frais affectés.\n";

/**
 * Encaissement au guichet, en suivant le même chemin que l'application :
 * paiement réussi, versement atomique, reçu PDF et notification du parent.
 * L'horloge de la session MariaDB est placée à la date historique (SET timestamp) :
 * CURRENT_TIMESTAMP donne alors la bonne date d'émission du reçu et d'envoi de la notification.
 */
$encaisser = static function (int $idEleve, int $idCategorie, float $montant, string $date) use ($db, $ids): void {
    $requete = $db->prepare('SELECT id_frais FROM frais WHERE id_eleve = ? AND id_categorie = ?');
    $requete->execute([$idEleve, $idCategorie]);
    $idFrais = (int) $requete->fetchColumn();

    $horloge = $db->prepare('SET timestamp = ?');
    $horloge->bindValue(1, strtotime($date), PDO::PARAM_INT);
    $horloge->execute();
    $db->beginTransaction();
    $idPaiement = Paiement::creer([
        'reference'     => Paiement::genererReference(new DateTimeImmutable($date)),
        'montant'       => $montant,
        'mode'          => 'especes',
        'statut'        => 'reussi',
        'date_paiement' => $date,
        'id_frais'      => $idFrais,
        'id_comptable'  => $ids['comptable'],
    ]);
    Frais::enregistrerVersement($idFrais, $montant);
    GenerateurRecu::generer($idPaiement);
    Notificateur::paiementConfirme($idPaiement);
    // Notifications historiques déjà lues par le parent.
    $db->prepare('UPDATE notification SET lu = TRUE WHERE id_notification = LAST_INSERT_ID()')->execute();
    $db->commit();
    $db->prepare('SET timestamp = DEFAULT')->execute();
};

// Élèves dans l'ordre d'inscription (id 1 à 24) ; historique déterministe.
$requete = $db->prepare('SELECT id_eleve FROM eleve ORDER BY id_eleve');
$requete->execute();
$idsEleves = $requete->fetchAll(PDO::FETCH_COLUMN);

// Opérations [élève, catégorie, montant, date], exécutées ensuite dans l'ordre chronologique
// pour que les numéros de reçus se suivent dans le temps.
$operations = [];
foreach ($idsEleves as $i => $idEleve) {
    $idEleve = (int) $idEleve;
    // Frais d'inscription : payés le jour de l'inscription par 20 élèves,
    // 2 partiels le lendemain (élèves 21-22), 2 impayés et échus (élèves 23-24).
    if ($i < 20) {
        $operations[] = [$idEleve, 'inscr', 50.00, $heure($datesInscription[$i], 8 + $i % 6, 10 + $i * 2)];
    } elseif ($i < 22) {
        $operations[] = [$idEleve, 'inscr', 25.00, $heure($datesInscription[$i] + 1, 10, 30 + $i)];
    }
    // Minerval T1 : 8 élèves soldés (dont un en deux fois), 6 en partiel, les autres impayés.
    if (in_array($i, [2, 4, 6, 8, 10, 12, 14], true)) {
        $operations[] = [$idEleve, 'mint1', 150.00, $heure(-12 + $i % 7, 9 + $i % 5, 5 + $i)];
    } elseif ($i === 16) {
        $operations[] = [$idEleve, 'mint1', 80.00, $heure(-15, 9, 40)];
        $operations[] = [$idEleve, 'mint1', 70.00, $heure(-4, 14, 20)];
    } elseif (in_array($i, [0, 3, 5, 9, 17, 19], true)) {
        $montant = [0 => 100.00, 3 => 75.00, 5 => 50.00, 9 => 120.00, 17 => 60.00, 19 => 90.00][$i];
        $operations[] = [$idEleve, 'mint1', $montant, $heure(-8 + $i % 6, 11, 15 + $i)];
    }
}
usort($operations, static fn(array $a, array $b): int => strcmp($a[3], $b[3]));
foreach ($operations as [$idEleve, $categorie, $montant, $date]) {
    $encaisser($idEleve, $idsCategories[$categorie], $montant, $date);
}
echo count($operations) . " paiements au guichet enregistrés, avec reçus PDF et notifications.\n";

// Le journal des e-mails repart à vide après l'installation.
file_put_contents(RACINE . '/storage/logs/mail.log', '');
echo "Terminé.\n";
