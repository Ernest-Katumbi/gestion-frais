<?php
/**
 * Formulaire de création / modification d'un utilisateur.
 * @var array|null $u utilisateur modifié (null en création)
 */
$edition = $u !== null;
$action = $edition ? url('/utilisateurs/' . (int) $u['id_utilisateur']) : url('/utilisateurs');
$estMoi = $edition && (int) $u['id_utilisateur'] === (int) utilisateur()['id_utilisateur'];
?>
<div class="page-header">
    <div>
        <h2><?= $edition ? 'Modifier ' . e($u['nom']) : 'Nouvel utilisateur' ?></h2>
        <p><?= $edition ? 'Mettez à jour les informations du compte.' : 'Créez un compte administrateur, comptable ou parent.' ?></p>
    </div>
</div>

<section class="card form">
    <form method="post" action="<?= $action ?>" class="card__body" data-validate>
        <?= csrf_champ() ?>
        <div class="form-grid">
            <?= View::partiel('champ', [
                'nom' => 'nom', 'libelle' => 'Nom complet', 'requis' => true, 'valeur' => $u['nom'] ?? '',
                'classe' => 'span-2', 'attributs' => 'minlength="2" maxlength="100" autocomplete="off" placeholder="Ex. Jean-Pierre Kabongo"',
            ]) ?>
            <?= View::partiel('champ', [
                'nom' => 'email', 'libelle' => 'Adresse e-mail', 'type' => 'email', 'requis' => true, 'valeur' => $u['email'] ?? '',
                'icone' => 'mail', 'attributs' => 'maxlength="100" autocomplete="off" placeholder="nom@exemple.cd"',
                'aide' => 'Sert d\'identifiant de connexion.',
            ]) ?>
            <?= View::partiel('champ', [
                'nom' => 'role', 'libelle' => 'Rôle', 'type' => 'select', 'requis' => true, 'valeur' => $u['role'] ?? 'parent',
                'options' => User::ROLES, 'attributs' => $estMoi ? 'disabled' : '',
                'aide' => $estMoi ? 'Vous ne pouvez pas modifier votre propre rôle.' : '',
            ]) ?>
            <?php if ($estMoi): ?><input type="hidden" name="role" value="<?= e($u['role']) ?>"><?php endif; ?>
            <?= View::partiel('champ', [
                'nom' => 'telephone', 'libelle' => 'Téléphone', 'type' => 'tel', 'valeur' => $u['telephone'] ?? '',
                'icone' => 'phone', 'attributs' => 'maxlength="20" pattern="\+?[0-9 ]{9,20}" data-pattern-message="Numéro invalide (ex. +243 97 123 4567)." placeholder="+243 97 123 4567"',
            ]) ?>
            <?php if ($edition): ?>
                <?= View::partiel('champ', [
                    'nom' => 'statut', 'libelle' => 'Statut du compte', 'type' => 'select', 'requis' => true, 'valeur' => $u['statut'],
                    'options' => ['actif' => 'Actif', 'inactif' => 'Désactivé'], 'attributs' => $estMoi ? 'disabled' : '',
                ]) ?>
                <?php if ($estMoi): ?><input type="hidden" name="statut" value="actif"><?php endif; ?>
            <?php endif; ?>
            <?= View::partiel('champ', [
                'nom' => 'adresse', 'libelle' => 'Adresse', 'valeur' => $u['adresse'] ?? '', 'classe' => $edition ? '' : 'span-2',
                'attributs' => 'maxlength="150" placeholder="Avenue, quartier, commune"',
            ]) ?>
        </div>

        <div class="form-section">
            <h3><?= $edition ? 'Réinitialiser le mot de passe' : 'Mot de passe' ?></h3>
            <p><?= $edition
                ? 'Laissez vide pour conserver le mot de passe actuel.'
                : 'Laissez vide pour générer un mot de passe provisoire, affiché une seule fois après la création.' ?></p>
        </div>
        <div class="form-grid">
            <?= View::partiel('champ', [
                'nom' => 'mot_de_passe', 'libelle' => $edition ? 'Nouveau mot de passe' : 'Mot de passe', 'type' => 'password',
                'aide' => 'Au moins 8 caractères, dont une lettre et un chiffre.',
                'attributs' => 'autocomplete="new-password" minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d).{8,}" data-pattern-message="Au moins 8 caractères, dont une lettre et un chiffre."',
            ]) ?>
        </div>

        <div class="form-actions">
            <a href="<?= url('/utilisateurs') ?>" class="btn btn-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary"><?= icone('check') ?> <?= $edition ? 'Enregistrer les modifications' : 'Créer le compte' ?></button>
        </div>
    </form>
</section>
