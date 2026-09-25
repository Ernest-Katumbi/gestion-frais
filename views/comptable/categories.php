<?php
/**
 * Catégories de frais.
 * @var array $categories
 */
?>
<div class="page-header">
    <div>
        <h2>Catégories de frais</h2>
        <p>Minerval, inscription, examens… Le montant par défaut est repris lors de l'affectation aux classes.</p>
    </div>
    <div class="page-header__actions">
        <a href="<?= url('/categories/nouveau') ?>" class="btn btn-primary"><?= icone('plus') ?> Nouvelle catégorie</a>
    </div>
</div>

<section class="card">
    <?php if ($categories === []): ?>
        <?= View::partiel('vide', [
            'icone'  => 'tag',
            'titre'  => 'Aucune catégorie de frais',
            'texte'  => 'Créez les catégories (minerval, inscription, examen…) avant de les affecter aux classes.',
            'action' => '<a href="' . url('/categories/nouveau') . '" class="btn btn-primary">' . icone('plus') . ' Créer une catégorie</a>',
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table-stack">
                <thead>
                    <tr>
                        <th>Catégorie</th>
                        <th>Code</th>
                        <th>Périodicité</th>
                        <th class="col-num">Montant par défaut</th>
                        <th class="col-num">Frais affectés</th>
                        <th class="col-num">Encaissé</th>
                        <th class="col-actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $c): ?>
                        <?php $id = (int) $c['id_categorie']; $utilisee = (int) $c['nb_frais'] > 0; ?>
                        <tr>
                            <td class="cell-main">
                                <div class="cell-user">
                                    <span class="stat__icon stat__icon--sm"><?= icone('tag') ?></span>
                                    <strong><?= e($c['libelle']) ?></strong>
                                </div>
                            </td>
                            <td data-label="Code"><code><?= e($c['code']) ?></code></td>
                            <td data-label="Périodicité"><?= e(CategorieFrais::PERIODICITES[$c['periodicite']]) ?></td>
                            <td data-label="Montant par défaut" class="col-num num fw-600"><?= e(formaterMontant($c['montant_defaut'])) ?></td>
                            <td data-label="Frais affectés" class="col-num num"><?= (int) $c['nb_frais'] ?></td>
                            <td data-label="Encaissé" class="col-num num"><?= e(formaterMontant($c['total_paye'])) ?></td>
                            <td class="col-actions">
                                <div class="actions">
                                    <a href="<?= url("/categories/$id/modifier") ?>" class="btn btn-ghost btn-icon btn-sm" title="Modifier" aria-label="Modifier <?= e($c['libelle']) ?>"><?= icone('pencil') ?></a>
                                    <form method="post" action="<?= url("/categories/$id/supprimer") ?>"
                                          data-confirm="<?= e($utilisee
                                              ? 'Cette catégorie est utilisée par ' . $c['nb_frais'] . ' frais : la suppression sera refusée.'
                                              : 'La catégorie « ' . $c['libelle'] . ' » sera supprimée définitivement.') ?>"
                                          data-confirm-titre="Supprimer cette catégorie ?" data-confirm-bouton="Supprimer">
                                        <?= csrf_champ() ?>
                                        <button type="submit" class="btn btn-ghost btn-icon btn-sm text-danger" data-no-loading title="Supprimer" aria-label="Supprimer <?= e($c['libelle']) ?>"><?= icone('trash') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
