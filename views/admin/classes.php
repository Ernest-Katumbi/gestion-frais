<?php
/**
 * Liste des classes.
 * @var array  $classes
 * @var string $recherche
 */
$totalEleves = array_sum(array_column($classes, 'nb_eleves'));
?>
<div class="page-header">
    <div>
        <h2>Classes</h2>
        <p><?= count($classes) ?> classe<?= count($classes) > 1 ? 's' : '' ?> · <?= $totalEleves ?> élève<?= $totalEleves > 1 ? 's' : '' ?> inscrit<?= $totalEleves > 1 ? 's' : '' ?></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= url('/classes/nouveau') ?>" class="btn btn-primary"><?= icone('plus') ?> Nouvelle classe</a>
    </div>
</div>

<section class="card">
    <form method="get" action="<?= url('/classes') ?>" class="toolbar" role="search">
        <div class="input-icon search">
            <?= icone('search') ?>
            <input type="search" name="q" value="<?= e($recherche) ?>" placeholder="Rechercher une classe ou une section…" aria-label="Rechercher">
        </div>
        <button type="submit" class="btn btn-secondary" data-no-loading><?= icone('search') ?> Rechercher</button>
        <?php if ($recherche !== ''): ?><a href="<?= url('/classes') ?>" class="btn btn-ghost">Réinitialiser</a><?php endif; ?>
    </form>

    <?php if ($classes === []): ?>
        <?= View::partiel('vide', [
            'icone'  => 'school',
            'titre'  => $recherche !== '' ? 'Aucune classe ne correspond' : 'Aucune classe pour l\'instant',
            'texte'  => $recherche !== '' ? 'Essayez une autre recherche.' : 'Créez les classes de l\'Institut avant d\'inscrire les élèves.',
            'action' => $recherche !== '' ? '' : '<a href="' . url('/classes/nouveau') . '" class="btn btn-primary">' . icone('plus') . ' Créer une classe</a>',
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table-stack">
                <thead>
                    <tr>
                        <th>Classe</th>
                        <th>Niveau</th>
                        <th>Section</th>
                        <th class="col-num">Élèves</th>
                        <th class="col-actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $c): ?>
                        <?php $id = (int) $c['id_classe']; $nb = (int) $c['nb_eleves']; ?>
                        <tr>
                            <td class="cell-main">
                                <div class="cell-user">
                                    <span class="avatar avatar-sm"><?= e($c['niveau']) ?></span>
                                    <strong><?= e($c['libelle']) ?></strong>
                                </div>
                            </td>
                            <td data-label="Niveau"><?= e($c['niveau']) ?></td>
                            <td data-label="Section"><?= e($c['section']) ?></td>
                            <td data-label="Élèves" class="col-num num">
                                <?php if ($nb > 0): ?>
                                    <a href="<?= url('/eleves', ['classe' => $id]) ?>"><?= $nb ?></a>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-actions">
                                <div class="actions">
                                    <a href="<?= url('/eleves/nouveau', ['classe' => $id]) ?>" class="btn btn-ghost btn-icon btn-sm" title="Inscrire un élève dans cette classe" aria-label="Inscrire un élève en <?= e($c['libelle']) ?>"><?= icone('user-plus') ?></a>
                                    <a href="<?= url("/classes/$id/modifier") ?>" class="btn btn-ghost btn-icon btn-sm" title="Modifier" aria-label="Modifier <?= e($c['libelle']) ?>"><?= icone('pencil') ?></a>
                                    <form method="post" action="<?= url("/classes/$id/supprimer") ?>"
                                          data-confirm="<?= $nb > 0
                                              ? e("Cette classe compte $nb élève" . ($nb > 1 ? 's' : '') . ' : la suppression sera refusée tant qu\'ils n\'auront pas changé de classe.')
                                              : e('La classe « ' . $c['libelle'] . ' » sera supprimée définitivement.') ?>"
                                          data-confirm-titre="Supprimer cette classe ?" data-confirm-bouton="Supprimer">
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
