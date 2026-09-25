<?php
/**
 * Formulaire de catégorie de frais.
 * @var array|null $categorie
 * @var bool       $utilisee
 */
$edition = $categorie !== null;
$action = $edition ? url('/categories/' . (int) $categorie['id_categorie']) : url('/categories');
?>
<div class="page-header">
    <div>
        <h2><?= $edition ? 'Modifier « ' . e($categorie['libelle']) . ' »' : 'Nouvelle catégorie de frais' ?></h2>
        <p>Le montant par défaut sera appliqué à chaque élève lors de l'affectation.</p>
    </div>
</div>

<?php if ($edition && !empty($utilisee)): ?>
    <div class="alert alert-info mb-3 form">
        <?= icone('info') ?>
        <div>Cette catégorie est déjà affectée à des élèves. Un nouveau montant par défaut ne s'appliquera qu'aux <strong style="display:inline">prochaines affectations</strong> : les frais existants conservent leur montant.</div>
    </div>
<?php endif; ?>

<section class="card form">
    <form method="post" action="<?= $action ?>" class="card__body" data-validate>
        <?= csrf_champ() ?>
        <div class="form-grid">
            <?= View::partiel('champ', [
                'nom' => 'libelle', 'libelle' => 'Libellé', 'requis' => true, 'valeur' => $categorie['libelle'] ?? '',
                'classe' => 'span-2', 'attributs' => 'minlength="3" maxlength="100" placeholder="Ex. Minerval 1er trimestre"',
            ]) ?>
            <?= View::partiel('champ', [
                'nom' => 'code', 'libelle' => 'Code', 'requis' => true, 'valeur' => $categorie['code'] ?? '',
                'attributs' => 'minlength="2" maxlength="20" pattern="[A-Za-z0-9_-]+" data-pattern-message="Lettres, chiffres, tirets et soulignés uniquement." placeholder="Ex. MIN-T1" style="text-transform:uppercase" autocomplete="off"',
                'aide' => 'Identifiant court, repris dans les rapports.',
            ]) ?>
            <?= View::partiel('champ', [
                'nom' => 'periodicite', 'libelle' => 'Périodicité', 'type' => 'select', 'requis' => true,
                'valeur' => $categorie['periodicite'] ?? '',
                'options' => ['' => 'Choisir…'] + CategorieFrais::PERIODICITES,
            ]) ?>
            <?= View::partiel('champ', [
                'nom' => 'montant_defaut', 'libelle' => 'Montant par défaut (' . DEVISE . ')', 'type' => 'number', 'requis' => true,
                'valeur' => $categorie['montant_defaut'] ?? '', 'classe' => 'span-2',
                'attributs' => 'min="0.01" step="0.01" max="99999999.99" inputmode="decimal" placeholder="0,00" class="input-montant"',
            ]) ?>
        </div>
        <div class="form-actions">
            <a href="<?= url('/categories') ?>" class="btn btn-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary"><?= icone('check') ?> <?= $edition ? 'Enregistrer' : 'Créer la catégorie' ?></button>
        </div>
    </form>
</section>
