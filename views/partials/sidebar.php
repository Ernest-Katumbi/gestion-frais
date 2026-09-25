<?php
/**
 * Barre latérale : navigation selon le rôle.
 * Seules les pages déjà disponibles (routes déclarées) sont affichées.
 * @var array $moi utilisateur connecté
 */
$menus = [
    'admin' => [
        'Pilotage' => [
            ['/admin', 'Tableau de bord', 'layout-dashboard'],
        ],
        'Administration' => [
            ['/utilisateurs', 'Utilisateurs', 'users'],
            ['/classes', 'Classes', 'school'],
            ['/eleves', 'Élèves', 'graduation-cap'],
        ],
    ],
    'comptable' => [
        'Pilotage' => [
            ['/comptable', 'Tableau de bord', 'layout-dashboard'],
            ['/rapports', 'Rapports', 'bar-chart'],
        ],
        'Encaissements' => [
            ['/paiements/guichet', 'Encaisser au guichet', 'banknote'],
            ['/paiements', 'Paiements', 'receipt'],
            ['/impayes', 'Impayés', 'alert-circle'],
        ],
        'Paramétrage' => [
            ['/categories', 'Catégories de frais', 'tag'],
            ['/frais', 'Affectation des frais', 'file-text'],
        ],
    ],
    'parent' => [
        'Mon espace' => [
            ['/parent', 'Accueil', 'home'],
            ['/parent/historique', 'Historique des paiements', 'receipt'],
            ['/notifications', 'Notifications', 'bell'],
        ],
    ],
];

$courant = cheminCourant();
$routeur = Router::instance();

/** Lien actif : chemin identique, ou sous-page (ex. /utilisateurs/3/modifier). */
$estActif = static function (string $chemin) use ($courant): bool {
    if ($chemin === $courant) {
        return true;
    }
    // /paiements ne doit pas être actif sur /paiements/guichet (lien distinct du menu).
    return str_starts_with($courant, $chemin . '/') && !in_array($courant, ['/paiements/guichet'], true);
};
?>
<aside class="sidebar" id="sidebar" aria-label="Navigation principale">
    <a class="sidebar__brand" href="<?= url(accueilDuRole($moi['role'])) ?>">
        <img src="<?= asset('img/logo.svg') ?>" alt="" width="38" height="38">
        <span>
            <strong><?= e(APP_NOM) ?></strong>
            <span>Gestion des frais scolaires</span>
        </span>
    </a>

    <nav class="sidebar__nav">
        <?php foreach ($menus[$moi['role']] ?? [] as $section => $liens): ?>
            <?php $liens = array_filter($liens, static fn(array $l): bool => $routeur->existe($l[0])); ?>
            <?php if ($liens === []) continue; ?>
            <div class="nav-section"><?= e($section) ?></div>
            <?php foreach ($liens as [$chemin, $libelle, $icone]): ?>
                <a href="<?= url($chemin) ?>" class="nav-link<?= $estActif($chemin) ? ' is-active' : '' ?>"<?= $estActif($chemin) ? ' aria-current="page"' : '' ?>>
                    <?= icone($icone) ?>
                    <span><?= e($libelle) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar__footer">
        <div class="flex">
            <span class="avatar avatar-sm"><?= e(initiales($moi['nom'])) ?></span>
            <div class="text-small" style="min-width:0">
                <strong class="fw-600"><?= e($moi['nom']) ?></strong><br>
                <span class="text-muted"><?= e(libelleRole($moi['role'])) ?></span>
            </div>
        </div>
    </div>
</aside>
