<?php
/**
 * Formulaire d'inscription / modification d'un élève.
 * Si l'e-mail du parent ne correspond à aucun compte, un compte parent est créé.
 * @var array|null $eleve
 * @var array      $classes               id => libellé
 * @var string     $matriculePropose
 * @var int        $classePreselectionnee
 */
$edition = $eleve !== null;
$action = $edition ? url('/eleves/' . (int) $eleve['id_eleve']) : url('/eleves');
$annuler = $edition ? url('/eleves/' . (int) $eleve['id_eleve']) : url('/eleves');
?>
<div class="page-header">
    <div>
        <h2><?= $edition ? 'Modifier ' . e($eleve['prenom'] . ' ' . $eleve['nom']) : 'Inscrire un élève' ?></h2>
        <p><?= $edition ? 'Mettez à jour la fiche de l\'élève.' : 'Renseignez l\'élève puis son parent ou tuteur.' ?></p>
    </div>
</div>

<section class="card form">
    <form method="post" action="<?= $action ?>" class="card__body" data-validate>
        <?= csrf_champ() ?>

        <h3 class="mb-2">Élève</h3>
        <div class="form-grid">
            <?= View::partiel('champ', [
                'nom' => 'matricule', 'libelle' => 'Matricule', 'requis' => true, 'valeur' => $matriculePropose,
                'attributs' => 'maxlength="50" autocomplete="off" pattern="[A-Za-z0-9]+(-[A-Za-z0-9]+)*" data-pattern-message="Lettres, chiffres et tirets uniquement (ex. IO-2026-025)." style="text-transform:uppercase"',
                'aide' => $edition ? '' : 'Numéro suivant proposé automatiquement.',
            ]) ?>
            <?= View::partiel('champ', [
                'nom' => 'date_inscription', 'libelle' => 'Date d\'inscription', 'type' => 'date', 'requis' => true,
                'valeur' => $eleve['date_inscription'] ?? date('Y-m-d'), 'attributs' => 'max="' . date('Y-m-d') . '"',
            ]) ?>
            <?= View::partiel('champ', [
                'nom' => 'nom', 'libelle' => 'Nom', 'requis' => true, 'valeur' => $eleve['nom'] ?? '',
                'attributs' => 'minlength="2" maxlength="50" autocomplete="off" placeholder="Ex. Kabongo"',
            ]) ?>
            <?= View::partiel('champ', [
                'nom' => 'prenom', 'libelle' => 'Prénom', 'requis' => true, 'valeur' => $eleve['prenom'] ?? '',
                'attributs' => 'minlength="2" maxlength="50" autocomplete="off" placeholder="Ex. Merveille"',
            ]) ?>
            <?= View::partiel('champ', [
                'nom' => 'id_classe', 'libelle' => 'Classe', 'type' => 'select', 'requis' => true, 'classe' => 'span-2',
                'valeur' => (string) ($classePreselectionnee ?: ''),
                'options' => ['' => 'Choisir une classe…'] + $classes,
            ]) ?>
        </div>

        <div class="form-section">
            <h3>Parent ou tuteur</h3>
            <p>Saisissez l'e-mail du parent. S'il possède déjà un compte (frères et sœurs), l'élève y sera rattaché ; sinon, un compte parent sera créé avec un mot de passe provisoire.</p>
        </div>

        <div class="form-grid" data-parent-lookup="<?= url('/eleves/parent') ?>">
            <?= View::partiel('champ', [
                'nom' => 'email_parent', 'libelle' => 'E-mail du parent', 'type' => 'email', 'requis' => true, 'classe' => 'span-2',
                'valeur' => $eleve['parent_email'] ?? '', 'icone' => 'mail',
                'attributs' => 'maxlength="100" autocomplete="off" placeholder="parent@exemple.cd"',
            ]) ?>

            <div class="span-2 parent-status hidden" data-parent-status aria-live="polite"></div>

            <div class="span-2 form-grid" data-parent-nouveau>
                <?= View::partiel('champ', [
                    'nom' => 'nom_parent', 'libelle' => 'Nom complet du parent', 'classe' => 'span-2',
                    'attributs' => 'minlength="2" maxlength="100" autocomplete="off" placeholder="Ex. Jean-Pierre Kabongo"',
                    'aide' => 'Obligatoire uniquement si le parent n\'a pas encore de compte.',
                ]) ?>
                <?= View::partiel('champ', [
                    'nom' => 'telephone_parent', 'libelle' => 'Téléphone', 'type' => 'tel', 'icone' => 'phone',
                    'attributs' => 'maxlength="20" pattern="\+?[0-9 ]{9,20}" data-pattern-message="Numéro invalide (ex. +243 97 123 4567)." placeholder="+243 97 123 4567"',
                ]) ?>
                <?= View::partiel('champ', [
                    'nom' => 'adresse_parent', 'libelle' => 'Adresse',
                    'attributs' => 'maxlength="150" placeholder="Avenue, quartier, commune"',
                ]) ?>
            </div>
        </div>

        <div class="form-actions">
            <a href="<?= $annuler ?>" class="btn btn-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary"><?= icone('check') ?> <?= $edition ? 'Enregistrer les modifications' : 'Inscrire l\'élève' ?></button>
        </div>
    </form>
</section>

<?php // Icônes utilisées par le message de recherche du parent (app.js). ?>
<template data-icone="user-plus"><?= icone('user-plus') ?></template>
<template data-icone="user-check"><?= icone('user-check') ?></template>
<template data-icone="alert-circle"><?= icone('alert-circle') ?></template>
