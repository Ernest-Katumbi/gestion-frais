<?php
/**
 * État vide illustré.
 * @var string      $icone
 * @var string      $titre
 * @var string      $texte
 * @var string|null $action HTML d'un bouton facultatif
 */
?>
<div class="empty">
    <span class="empty__art"><?= icone($icone ?? 'inbox') ?></span>
    <h3><?= e($titre) ?></h3>
    <p><?= e($texte ?? '') ?></p>
    <?= $action ?? '' ?>
</div>
