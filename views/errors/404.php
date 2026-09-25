<?php /** @var string $message */ ?>
<div class="card error-page">
    <div class="card__body">
        <span class="code">404</span>
        <h1>Page introuvable</h1>
        <p><?= e($message !== '' && $message !== 'Page introuvable.' ? $message : 'La page demandée n\'existe pas ou a été déplacée.') ?></p>
        <?= View::partiel('erreur_actions') ?>
    </div>
</div>
