<?php
/**
 * Notifications de l'utilisateur connecté.
 * @var array $notifications
 * @var int   $nonLues
 * @var array $pagination
 */
?>
<div class="page-header">
    <div>
        <h2>Notifications</h2>
        <p><?= $nonLues > 0 ? $nonLues . ' notification' . ($nonLues > 1 ? 's' : '') . ' non lue' . ($nonLues > 1 ? 's' : '') : 'Tout est lu.' ?></p>
    </div>
    <?php if ($nonLues > 0): ?>
        <div class="page-header__actions">
            <form method="post" action="<?= url('/notifications/tout-lire') ?>">
                <?= csrf_champ() ?>
                <button type="submit" class="btn btn-secondary"><?= icone('check') ?> Tout marquer comme lu</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<section class="card">
    <?php if ($notifications === []): ?>
        <?= View::partiel('vide', [
            'icone' => 'bell', 'titre' => 'Aucune notification pour l\'instant',
            'texte' => 'Les confirmations de paiement et les rappels d\'échéance apparaîtront ici.',
        ]) ?>
    <?php else: ?>
        <ul class="list notif-page">
            <?php foreach ($notifications as $n): ?>
                <li class="notif-item<?= $n['lu'] ? '' : ' is-unread' ?>">
                    <span class="notif-item__icon<?= $n['type'] === 'echeance' ? ' is-echeance' : (str_starts_with($n['message'], 'Échec') ? ' is-echec' : '') ?>">
                        <?= icone($n['type'] === 'echeance' ? 'calendar' : (str_starts_with($n['message'], 'Échec') ? 'alert-circle' : 'check-circle'), 'icon-sm') ?>
                    </span>
                    <div class="list__main">
                        <span class="notif-item__texte"><?= e($n['message']) ?></span>
                        <span class="notif-item__date"><?= $n['type'] === 'echeance' ? 'Rappel d\'échéance' : 'Paiement' ?> · <?= e(formaterDateHeure($n['date_envoi'])) ?></span>
                    </div>
                    <?php if (!$n['lu']): ?>
                        <form method="post" action="<?= url('/notifications/' . (int) $n['id_notification'] . '/lire') ?>">
                            <?= csrf_champ() ?>
                            <input type="hidden" name="retour" value="<?= e(cheminCourant()) ?>">
                            <button type="submit" class="btn btn-ghost btn-sm" data-no-loading title="Marquer comme lu"><?= icone('check') ?><span class="hide-mobile"> Lu</span></button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?= View::partiel('pagination', ['pagination' => $pagination, 'libelle' => 'notifications']) ?>
</section>
