<?php
/**
 * Encadré affichant, une seule fois, le mot de passe provisoire d'un compte créé.
 * @var array{nom:string, email:string, mot_de_passe:string} $provisoire
 */
?>
<div class="alert alert-olive mb-3" role="status">
    <?= icone('key') ?>
    <div style="flex:1; min-width:0">
        <strong>Mot de passe provisoire de <?= e($provisoire['nom']) ?></strong>
        <p class="mb-0">Communiquez-le au titulaire du compte (<?= e($provisoire['email']) ?>). Il ne sera plus affiché ; une copie de l'e-mail de bienvenue a été enregistrée dans le journal des e-mails.</p>
        <div class="secret-box">
            <code><?= e($provisoire['mot_de_passe']) ?></code>
            <button type="button" class="btn btn-secondary btn-sm" data-copy="<?= e($provisoire['mot_de_passe']) ?>"><?= icone('copy') ?> Copier</button>
        </div>
    </div>
</div>
