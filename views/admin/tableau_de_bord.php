<?php
/**
 * Tableau de bord de l'administrateur.
 * @var array $parRole         nombre d'utilisateurs actifs par rôle
 * @var int   $inactifs
 * @var int   $nbEleves
 * @var array $classes         classes avec leur effectif
 * @var array $derniersEleves
 * @var array $derniers        derniers comptes créés
 */
$moi = utilisateur();
$effectifMax = max([1, ...array_map('intval', array_column($classes, 'nb_eleves'))]);
?>
<section class="card welcome mb-3">
    <div>
        <h2>Bonjour, <?= e($moi['nom']) ?></h2>
        <p>Voici la situation des inscriptions et des comptes de l'<?= e(APP_NOM) ?>.</p>
    </div>
    <img class="welcome__art" src="<?= asset('img/logo-institut-blanc.png') ?>" alt="">
</section>

<div class="grid grid-4 mb-3">
    <a class="card stat stat-link" href="<?= url('/eleves') ?>">
        <span class="stat__icon"><?= icone('graduation-cap') ?></span>
        <div>
            <p class="stat__label">Élèves inscrits</p>
            <p class="stat__value"><?= $nbEleves ?></p>
            <p class="stat__hint">répartis dans <?= count($classes) ?> classe<?= count($classes) > 1 ? 's' : '' ?></p>
        </div>
    </a>
    <a class="card stat stat-link" href="<?= url('/utilisateurs', ['role' => 'parent']) ?>">
        <span class="stat__icon is-info"><?= icone('users') ?></span>
        <div>
            <p class="stat__label">Parents actifs</p>
            <p class="stat__value"><?= $parRole['parent'] ?></p>
            <p class="stat__hint">comptes parents</p>
        </div>
    </a>
    <a class="card stat stat-link" href="<?= url('/utilisateurs') ?>">
        <span class="stat__icon is-success"><?= icone('shield-check') ?></span>
        <div>
            <p class="stat__label">Personnel</p>
            <p class="stat__value"><?= $parRole['admin'] + $parRole['comptable'] ?></p>
            <p class="stat__hint"><?= $parRole['admin'] ?> admin. · <?= $parRole['comptable'] ?> comptable<?= $parRole['comptable'] > 1 ? 's' : '' ?></p>
        </div>
    </a>
    <a class="card stat stat-link" href="<?= url('/utilisateurs', ['statut' => 'inactif']) ?>">
        <span class="stat__icon is-warning"><?= icone('user-x') ?></span>
        <div>
            <p class="stat__label">Comptes désactivés</p>
            <p class="stat__value"><?= $inactifs ?></p>
            <p class="stat__hint">historique conservé</p>
        </div>
    </a>
</div>

<section class="card mb-3">
    <div class="card__header">
        <h3>Situation financière</h3>
        <span class="text-small text-muted">encaissements du mois en cours · soldes à ce jour</span>
    </div>
    <div class="finances">
        <div><span>Encaissé ce mois-ci</span><strong class="num"><?= e(formaterMontant($kpi['somme'])) ?></strong></div>
        <div><span>Total impayé</span><strong class="num text-danger"><?= e(formaterMontant($kpi['reste'])) ?></strong></div>
        <div>
            <span>Taux de recouvrement</span>
            <strong class="num"><?= e(number_format($kpi['taux'], 1, ',', ' ')) ?> %</strong>
            <span class="progress"><span class="progress__bar is-primaire" style="width: <?= min(100, round($kpi['taux'])) ?>%"></span></span>
        </div>
        <div><span>Part des paiements en ligne</span><strong class="num"><?= e(number_format($kpi['part_ligne'], 1, ',', ' ')) ?> %</strong></div>
    </div>
</section>

<div class="grid grid-main-side mb-3">
    <section class="card">
        <div class="card__header">
            <h3>Effectifs par classe</h3>
            <a href="<?= url('/classes') ?>" class="btn btn-ghost btn-sm">Gérer les classes <?= icone('arrow-right') ?></a>
        </div>
        <?php if ($classes === []): ?>
            <?= View::partiel('vide', [
                'icone' => 'school', 'titre' => 'Aucune classe', 'texte' => 'Créez les classes de l\'Institut pour commencer les inscriptions.',
                'action' => '<a href="' . url('/classes/nouveau') . '" class="btn btn-primary">' . icone('plus') . ' Créer une classe</a>',
            ]) ?>
        <?php else: ?>
            <ul class="bars card__body">
                <?php foreach ($classes as $c): ?>
                    <?php $nb = (int) $c['nb_eleves']; ?>
                    <li>
                        <a class="bars__label" href="<?= url('/eleves', ['classe' => (int) $c['id_classe']]) ?>"><?= e($c['libelle']) ?></a>
                        <span class="bars__track"><span class="bars__fill" style="width: <?= round($nb / $effectifMax * 100, 1) ?>%"></span></span>
                        <span class="bars__value num"><?= $nb ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <aside class="card">
        <div class="card__header"><h3>Actions rapides</h3></div>
        <div class="card__body grid">
            <a class="btn btn-primary btn-block" href="<?= url('/eleves/nouveau') ?>"><?= icone('graduation-cap') ?> Inscrire un élève</a>
            <a class="btn btn-secondary btn-block" href="<?= url('/classes/nouveau') ?>"><?= icone('school') ?> Créer une classe</a>
            <a class="btn btn-secondary btn-block" href="<?= url('/utilisateurs/nouveau') ?>"><?= icone('user-plus') ?> Créer un utilisateur</a>
        </div>
    </aside>
</div>

<div class="grid grid-2">
    <section class="card">
        <div class="card__header">
            <h3>Dernières inscriptions</h3>
            <a href="<?= url('/eleves') ?>" class="btn btn-ghost btn-sm">Tout voir <?= icone('arrow-right') ?></a>
        </div>
        <?php if ($derniersEleves === []): ?>
            <?= View::partiel('vide', ['icone' => 'graduation-cap', 'titre' => 'Aucun élève inscrit', 'texte' => 'Les nouvelles inscriptions apparaîtront ici.']) ?>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($derniersEleves as $el): ?>
                    <li>
                        <span class="avatar avatar-sm"><?= e(initiales($el['prenom'] . ' ' . $el['nom'])) ?></span>
                        <a class="list__main" href="<?= url('/eleves/' . (int) $el['id_eleve']) ?>">
                            <strong><?= e($el['prenom'] . ' ' . $el['nom']) ?></strong>
                            <span><?= e($el['matricule']) ?> · inscrit(e) le <?= e(formaterDate($el['date_inscription'])) ?></span>
                        </a>
                        <span class="badge badge-parent no-dot"><?= e($el['classe']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card__header">
            <h3>Derniers comptes créés</h3>
            <a href="<?= url('/utilisateurs') ?>" class="btn btn-ghost btn-sm">Tout voir <?= icone('arrow-right') ?></a>
        </div>
        <ul class="list">
            <?php foreach ($derniers as $u): ?>
                <li>
                    <span class="avatar avatar-sm"><?= e(initiales($u['nom'])) ?></span>
                    <div class="list__main">
                        <strong><?= e($u['nom']) ?></strong>
                        <span><?= e($u['email']) ?> · créé le <?= e(formaterDate($u['date_creation'])) ?></span>
                    </div>
                    <span class="badge badge-<?= e($u['role']) ?> no-dot"><?= e(libelleRole($u['role'])) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>
