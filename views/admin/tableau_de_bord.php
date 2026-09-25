<?php
/**
 * Tableau de bord de l'administrateur.
 * @var array $parRole  nombre d'utilisateurs actifs par rôle
 * @var int   $inactifs
 * @var array $derniers derniers comptes créés
 */
$moi = utilisateur();
?>
<section class="card welcome mb-3">
    <div>
        <h2>Bonjour, <?= e($moi['nom']) ?></h2>
        <p>Voici un aperçu des comptes de la plateforme de l'<?= e(APP_NOM) ?>.</p>
    </div>
    <img class="welcome__art" src="<?= asset('img/logo.svg') ?>" alt="">
</section>

<div class="grid grid-4 mb-3">
    <div class="card stat">
        <span class="stat__icon"><?= icone('users') ?></span>
        <div>
            <p class="stat__label">Parents actifs</p>
            <p class="stat__value"><?= $parRole['parent'] ?></p>
        </div>
    </div>
    <div class="card stat">
        <span class="stat__icon is-info"><?= icone('wallet') ?></span>
        <div>
            <p class="stat__label">Comptables</p>
            <p class="stat__value"><?= $parRole['comptable'] ?></p>
        </div>
    </div>
    <div class="card stat">
        <span class="stat__icon is-success"><?= icone('shield-check') ?></span>
        <div>
            <p class="stat__label">Administrateurs</p>
            <p class="stat__value"><?= $parRole['admin'] ?></p>
        </div>
    </div>
    <div class="card stat">
        <span class="stat__icon is-warning"><?= icone('user-x') ?></span>
        <div>
            <p class="stat__label">Comptes désactivés</p>
            <p class="stat__value"><?= $inactifs ?></p>
        </div>
    </div>
</div>

<div class="grid grid-main-side">
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
                    <span class="badge badge-<?= e($u['role']) ?>"><?= e(libelleRole($u['role'])) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <aside class="card">
        <div class="card__header"><h3>Actions rapides</h3></div>
        <div class="card__body grid">
            <a class="btn btn-primary btn-block" href="<?= url('/utilisateurs/nouveau') ?>"><?= icone('user-plus') ?> Créer un utilisateur</a>
            <?php if (Router::instance()->existe('/eleves/nouveau')): ?>
                <a class="btn btn-secondary btn-block" href="<?= url('/eleves/nouveau') ?>"><?= icone('graduation-cap') ?> Inscrire un élève</a>
            <?php endif; ?>
            <?php if (Router::instance()->existe('/classes')): ?>
                <a class="btn btn-secondary btn-block" href="<?= url('/classes') ?>"><?= icone('school') ?> Gérer les classes</a>
            <?php endif; ?>
        </div>
    </aside>
</div>
