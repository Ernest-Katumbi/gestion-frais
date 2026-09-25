<?php
/**
 * Formulaire de création / modification d'une classe.
 * @var array|null $classe
 */
$edition = $classe !== null;
$action = $edition ? url('/classes/' . (int) $classe['id_classe']) : url('/classes');
?>
<div class="page-header">
    <div>
        <h2><?= $edition ? 'Modifier « ' . e($classe['libelle']) . ' »' : 'Nouvelle classe' ?></h2>
        <p>Le libellé identifie la classe dans toute l'application (listes, reçus, rapports).</p>
    </div>
</div>

<section class="card form">
    <form method="post" action="<?= $action ?>" class="card__body" data-validate>
        <?= csrf_champ() ?>
        <div class="form-grid">
            <?= View::partiel('champ', [
                'nom' => 'niveau', 'libelle' => 'Niveau', 'type' => 'select', 'requis' => true,
                'valeur' => $classe['niveau'] ?? '',
                'options' => ['' => 'Choisir…'] + array_combine(Classe::NIVEAUX, Classe::NIVEAUX),
            ]) ?>
            <?= View::partiel('champ', [
                'nom' => 'section', 'libelle' => 'Section', 'requis' => true, 'valeur' => $classe['section'] ?? '',
                'attributs' => 'list="sections" minlength="2" maxlength="50" placeholder="Ex. Scientifique" autocomplete="off"',
            ]) ?>
            <datalist id="sections">
                <?php foreach (Classe::SECTIONS as $section): ?><option value="<?= e($section) ?>"><?php endforeach; ?>
            </datalist>
            <?= View::partiel('champ', [
                'nom' => 'libelle', 'libelle' => 'Libellé', 'valeur' => $classe['libelle'] ?? '', 'classe' => 'span-2',
                'attributs' => 'maxlength="50" placeholder="Ex. 4e Commerciale"',
                'aide' => 'Laissez vide pour utiliser « niveau + section ». Ajoutez une lettre pour distinguer deux classes parallèles (ex. 3e Scientifique B).',
            ]) ?>
        </div>
        <div class="form-actions">
            <a href="<?= url('/classes') ?>" class="btn btn-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary"><?= icone('check') ?> <?= $edition ? 'Enregistrer' : 'Créer la classe' ?></button>
        </div>
    </form>
</section>
