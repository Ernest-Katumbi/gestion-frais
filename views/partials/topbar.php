<?php
/**
 * Barre supérieure : titre, fil d'Ariane, cloche de notifications, menu utilisateur.
 * @var array  $moi
 * @var string $titre
 * @var array  $fil
 */
$nonLues = Notification::compterNonLues((int) $moi['id_utilisateur']);
$notifications = Notification::dernieres((int) $moi['id_utilisateur'], 6);
?>
<header class="topbar">
    <button type="button" class="icon-btn menu-toggle" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false" aria-label="Ouvrir le menu">
        <?= icone('menu') ?>
    </button>

    <div class="topbar__title">
        <?php if (!empty($fil)): ?>
            <ol class="breadcrumb" aria-label="Fil d'Ariane">
                <li><a href="<?= url(accueilDuRole($moi['role'])) ?>">Accueil</a></li>
                <?php foreach ($fil as [$libelle, $chemin]): ?>
                    <li aria-hidden="true"><?= icone('chevron-right') ?></li>
                    <li><?= $chemin ? '<a href="' . url($chemin) . '">' . e($libelle) . '</a>' : e($libelle) ?></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
        <h1><?= e($titre) ?></h1>
    </div>

    <div class="topbar__actions">
        <div class="dropdown">
            <button type="button" class="icon-btn" data-dropdown-toggle aria-expanded="false"
                    aria-label="Notifications<?= $nonLues ? " ($nonLues non lues)" : '' ?>">
                <?= icone('bell') ?>
                <?php if ($nonLues > 0): ?><span class="dot"><?= $nonLues > 9 ? '9+' : $nonLues ?></span><?php endif; ?>
            </button>
            <div class="dropdown__menu notif-menu">
                <div class="dropdown__header">
                    <strong>Notifications</strong>
                    <?php if ($nonLues > 0): ?><span class="badge badge-en_attente no-dot"><?= $nonLues ?> non lue<?= $nonLues > 1 ? 's' : '' ?></span><?php endif; ?>
                </div>
                <?php if ($notifications === []): ?>
                    <div class="notif-empty">
                        <?= icone('inbox', 'icon-lg') ?>
                        <p class="mt-1 mb-0">Aucune notification pour l'instant.</p>
                    </div>
                <?php else: ?>
                    <div class="notif-list">
                        <?php foreach ($notifications as $n): ?>
                            <div class="notif-item<?= $n['lu'] ? '' : ' is-unread' ?>">
                                <span class="notif-item__icon<?= $n['type'] === 'echeance' ? ' is-echeance' : '' ?>">
                                    <?= icone($n['type'] === 'echeance' ? 'calendar' : 'check-circle', 'icon-sm') ?>
                                </span>
                                <div>
                                    <?= e($n['message']) ?>
                                    <span class="notif-item__date"><?= e(formaterDateHeure($n['date_envoi'])) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="notif-menu__pied">
                    <a href="<?= url('/notifications') ?>" class="btn btn-ghost btn-sm">Voir toutes les notifications</a>
                    <?php if ($nonLues > 0): ?>
                        <form method="post" action="<?= url('/notifications/tout-lire') ?>">
                            <?= csrf_champ() ?>
                            <input type="hidden" name="retour" value="<?= e(cheminCourant()) ?>">
                            <button type="submit" class="btn btn-ghost btn-sm" data-no-loading><?= icone('check', 'icon-sm') ?> Tout marquer comme lu</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="dropdown">
            <button type="button" class="user-chip" data-dropdown-toggle aria-expanded="false" aria-label="Menu utilisateur">
                <span class="avatar"><?= e(initiales($moi['nom'])) ?></span>
                <span class="user-chip__name">
                    <?= e($moi['nom']) ?>
                    <span class="user-chip__role"><?= e(libelleRole($moi['role'])) ?></span>
                </span>
                <?= icone('chevron-down', 'icon-sm') ?>
            </button>
            <div class="dropdown__menu">
                <div class="dropdown__header">
                    <strong class="fw-600"><?= e($moi['nom']) ?></strong>
                    <div class="text-small text-muted"><?= e($moi['email']) ?></div>
                </div>
                <a class="dropdown__item" href="<?= url('/compte') ?>"><?= icone('key') ?> Mon compte</a>
                <form method="post" action="<?= url('/deconnexion') ?>">
                    <?= csrf_champ() ?>
                    <button type="submit" class="dropdown__item is-danger" data-no-loading><?= icone('log-out') ?> Se déconnecter</button>
                </form>
            </div>
        </div>
    </div>
</header>
