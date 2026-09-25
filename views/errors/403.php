<?php /** @var string $message */ ?>
<div class="card error-page">
    <div class="card__body">
        <span class="code">403</span>
        <h1>Accès refusé</h1>
        <p><?= e($message !== '' ? $message : 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.') ?></p>
        <?= View::partiel('erreur_actions') ?>
    </div>
</div>
