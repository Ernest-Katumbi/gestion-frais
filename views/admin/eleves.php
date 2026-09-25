<?php
/**
 * Liste des élèves.
 * @var array      $eleves
 * @var array      $classes    id => libellé
 * @var array      $filtres    q, classe
 * @var array      $pagination
 * @var array|null $provisoire
 */
$filtreActif = $filtres['q'] !== '' || $filtres['classe'] > 0;
?>
<div class="page-header">
    <div>
        <h2>Élèves</h2>
        <p>Élèves inscrits à l'<?= e(APP_NOM) ?>, avec leur classe et leur parent.</p>
    </div>
    <div class="page-header__actions">
        <a href="<?= url('/eleves/nouveau', $filtres['classe'] > 0 ? ['classe' => $filtres['classe']] : []) ?>" class="btn btn-primary"><?= icone('user-plus') ?> Inscrire un élève</a>
    </div>
</div>

<?php if ($provisoire): ?>
    <?= View::partiel('mot_de_passe_provisoire', ['provisoire' => $provisoire]) ?>
<?php endif; ?>

<section class="card">
    <form method="get" action="<?= url('/eleves') ?>" class="toolbar" role="search">
        <div class="input-icon search">
            <?= icone('search') ?>
            <input type="search" name="q" value="<?= e($filtres['q']) ?>" placeholder="Matricule, nom de l'élève ou du parent…" aria-label="Rechercher">
        </div>
        <select name="classe" data-autosubmit aria-label="Filtrer par classe">
            <option value="0">Toutes les classes</option>
            <?php foreach ($classes as $id => $libelle): ?>
                <option value="<?= (int) $id ?>"<?= $filtres['classe'] === (int) $id ? ' selected' : '' ?>><?= e($libelle) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary" data-no-loading><?= icone('filter') ?> Filtrer</button>
        <?php if ($filtreActif): ?><a href="<?= url('/eleves') ?>" class="btn btn-ghost">Réinitialiser</a><?php endif; ?>
    </form>

    <?php if ($eleves === []): ?>
        <?= View::partiel('vide', [
            'icone'  => 'graduation-cap',
            'titre'  => $filtreActif ? 'Aucun élève ne correspond' : 'Aucun élève inscrit pour l\'instant',
            'texte'  => $filtreActif ? 'Essayez une autre recherche ou une autre classe.' : 'Inscrivez un premier élève : son compte parent sera créé automatiquement si nécessaire.',
            'action' => $filtreActif ? '' : '<a href="' . url('/eleves/nouveau') . '" class="btn btn-primary">' . icone('user-plus') . ' Inscrire un élève</a>',
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table-stack">
                <thead>
                    <tr>
                        <th>Élève</th>
                        <th>Matricule</th>
                        <th>Classe</th>
                        <th>Parent</th>
                        <th>Inscrit le</th>
                        <th class="col-actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eleves as $el): ?>
                        <?php $id = (int) $el['id_eleve']; $nom = $el['prenom'] . ' ' . $el['nom']; ?>
                        <tr>
                            <td class="cell-main">
                                <a class="cell-user" href="<?= url("/eleves/$id") ?>">
                                    <span class="avatar avatar-sm"><?= e(initiales($nom)) ?></span>
                                    <div>
                                        <strong><?= e($el['nom']) ?> <?= e($el['prenom']) ?></strong>
                                        <span class="hide-desktop"><?= e($el['matricule']) ?></span>
                                    </div>
                                </a>
                            </td>
                            <td data-label="Matricule" class="num hide-mobile"><?= e($el['matricule']) ?></td>
                            <td data-label="Classe"><span class="badge badge-parent no-dot"><?= e($el['classe']) ?></span></td>
                            <td data-label="Parent">
                                <div>
                                    <?= e($el['parent_nom']) ?>
                                    <?php if ($el['parent_telephone']): ?><span class="text-small text-muted d-block num"><?= e($el['parent_telephone']) ?></span><?php endif; ?>
                                </div>
                            </td>
                            <td data-label="Inscrit le" class="num"><?= e(formaterDate($el['date_inscription'])) ?></td>
                            <td class="col-actions">
                                <div class="actions">
                                    <a href="<?= url("/eleves/$id") ?>" class="btn btn-ghost btn-icon btn-sm" title="Voir la fiche" aria-label="Voir la fiche de <?= e($nom) ?>"><?= icone('eye') ?></a>
                                    <a href="<?= url("/eleves/$id/modifier") ?>" class="btn btn-ghost btn-icon btn-sm" title="Modifier" aria-label="Modifier <?= e($nom) ?>"><?= icone('pencil') ?></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= View::partiel('pagination', ['pagination' => $pagination, 'libelle' => 'élèves']) ?>
</section>
