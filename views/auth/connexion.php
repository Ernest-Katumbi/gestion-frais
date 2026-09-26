<?php /** Page de connexion. */ ?>
<div class="auth-card">
    <div class="auth-brand">
        <img src="<?= asset('img/logo-institut.png') ?>" alt="Logo de l'<?= e(APP_NOM) ?>" width="116" height="116">
        <h1><?= e(APP_NOM) ?></h1>
        <p><?= e(APP_MENTION) ?> · Kolwezi</p>
        <p class="auth-brand__app">Gestion des frais scolaires</p>
    </div>

    <div class="card">
        <h2 class="mb-1">Connexion</h2>
        <p class="text-muted text-small mb-2">Connectez-vous avec l'adresse e-mail communiquée par l'Institut.</p>

        <form method="post" action="<?= url('/connexion') ?>" data-validate>
            <?= csrf_champ() ?>
            <?= View::partiel('champ', [
                'nom' => 'email', 'libelle' => 'Adresse e-mail', 'type' => 'email', 'requis' => true,
                'icone' => 'mail', 'attributs' => 'autocomplete="username" autofocus placeholder="vous@exemple.cd" maxlength="100"',
            ]) ?>
            <?= View::partiel('champ', [
                'nom' => 'mot_de_passe', 'libelle' => 'Mot de passe', 'type' => 'password', 'requis' => true,
                'attributs' => 'autocomplete="current-password" placeholder="Votre mot de passe"',
            ]) ?>
            <button type="submit" class="btn btn-primary btn-lg btn-block">
                <?= icone('log-in') ?> Se connecter
            </button>
        </form>

        <?php if (APP_DEMO): ?>
            <details class="demo-accounts">
                <summary>Comptes de démonstration</summary>
                <table>
                    <?php foreach (['admin@oliviers.cd' => 'Administrateur', 'comptable@oliviers.cd' => 'Comptable', 'parent@oliviers.cd' => 'Parent'] as $email => $role): ?>
                        <tr>
                            <td class="text-muted"><?= e($role) ?></td>
                            <td class="text-right">
                                <button type="button" data-demo-email="<?= e($email) ?>" data-demo-password="Demo@2026"><?= e($email) ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr><td class="text-muted">Mot de passe</td><td class="text-right"><code>Demo@2026</code></td></tr>
                </table>
            </details>
        <?php endif; ?>
    </div>

    <p class="auth-footer"><?= e(APP_NOM) ?> · <?= e(APP_VILLE) ?></p>
</div>
