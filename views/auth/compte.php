<?php
/** Mon compte : informations personnelles et changement du mot de passe. @var array $utilisateur */
?>
<div class="grid grid-main-side">
    <section class="card">
        <div class="card__header"><h3>Changer mon mot de passe</h3></div>
        <form method="post" action="<?= url('/compte/mot-de-passe') ?>" class="card__body" data-validate>
            <?= csrf_champ() ?>
            <div class="form-grid">
                <?= View::partiel('champ', [
                    'nom' => 'mot_de_passe_actuel', 'libelle' => 'Mot de passe actuel', 'type' => 'password',
                    'requis' => true, 'classe' => 'span-2', 'attributs' => 'autocomplete="current-password"',
                ]) ?>
                <?= View::partiel('champ', [
                    'nom' => 'mot_de_passe', 'libelle' => 'Nouveau mot de passe', 'type' => 'password', 'requis' => true,
                    'aide' => 'Au moins 8 caractères, dont une lettre et un chiffre.',
                    'attributs' => 'autocomplete="new-password" minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d).{8,}" data-pattern-message="Au moins 8 caractères, dont une lettre et un chiffre."',
                ]) ?>
                <?= View::partiel('champ', [
                    'nom' => 'mot_de_passe_confirmation', 'libelle' => 'Confirmation', 'type' => 'password', 'requis' => true,
                    'attributs' => 'autocomplete="new-password" data-match="mot_de_passe"',
                ]) ?>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= icone('check') ?> Mettre à jour</button>
            </div>
        </form>
    </section>

    <aside class="card">
        <div class="card__header"><h3>Mes informations</h3></div>
        <div class="card__body">
            <div class="flex mb-2">
                <span class="avatar"><?= e(initiales($utilisateur['nom'])) ?></span>
                <div>
                    <strong><?= e($utilisateur['nom']) ?></strong><br>
                    <span class="badge badge-<?= e($utilisateur['role']) ?>"><?= e(libelleRole($utilisateur['role'])) ?></span>
                </div>
            </div>
            <dl class="dl">
                <dt>E-mail</dt><dd><?= e($utilisateur['email']) ?></dd>
                <dt>Téléphone</dt><dd><?= e($utilisateur['telephone'] ?: '—') ?></dd>
                <dt>Adresse</dt><dd><?= e($utilisateur['adresse'] ?: '—') ?></dd>
                <dt>Membre depuis</dt><dd><?= e(formaterDate($utilisateur['date_creation'])) ?></dd>
            </dl>
            <p class="text-small text-muted mt-2 mb-0">Pour modifier ces informations, adressez-vous à l'administration de l'Institut.</p>
        </div>
    </aside>
</div>
