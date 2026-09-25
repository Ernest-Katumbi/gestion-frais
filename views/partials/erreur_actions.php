<?php
/** Boutons de retour des pages d'erreur. */
try {
    $moi = utilisateur();
} catch (Throwable) {
    $moi = null; // base indisponible : on n'affiche que le lien de connexion
}
?>
<div class="actions">
    <?php if ($moi): ?>
        <a class="btn btn-primary" href="<?= url(accueilDuRole($moi['role'])) ?>"><?= icone('home') ?> Retour à l'accueil</a>
    <?php else: ?>
        <a class="btn btn-primary" href="<?= url('/connexion') ?>"><?= icone('arrow-left') ?> Page de connexion</a>
    <?php endif; ?>
</div>
