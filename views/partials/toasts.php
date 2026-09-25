<?php
/** Messages flash affichés sous forme de toasts (disparition automatique). */
$icones = ['succes' => 'check-circle', 'erreur' => 'alert-circle', 'avertissement' => 'alert-triangle', 'info' => 'info'];
$messages = Session::lireFlash('messages', []);
?>
<div class="toasts" aria-live="polite" aria-atomic="false">
    <?php foreach ($messages as $m): ?>
        <?php $type = isset($icones[$m['type']]) ? $m['type'] : 'info'; ?>
        <div class="toast toast-<?= e($type) ?>" role="<?= $type === 'erreur' ? 'alert' : 'status' ?>">
            <?= icone($icones[$type]) ?>
            <div class="toast__text"><?= e($m['texte']) ?></div>
            <button type="button" class="icon-btn toast__close" aria-label="Fermer"><?= icone('x', 'icon-sm') ?></button>
        </div>
    <?php endforeach; ?>
</div>
