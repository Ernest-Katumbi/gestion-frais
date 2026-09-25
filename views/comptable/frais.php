<?php
/**
 * Affectation des frais aux classes + récapitulatif des affectations existantes.
 * @var array $affectations
 * @var array $categories
 * @var array $classes
 */
$ancienne = Session::lireFlash('old', []);
$classesCochees = array_map('intval', (array) ($ancienne['classes'] ?? []));
$erreurClasses = erreur('classes');
?>
<div class="page-header">
    <div>
        <h2>Affectation des frais</h2>
        <p>Affectez une catégorie de frais à une ou plusieurs classes : chaque élève reçoit un frais « impayé ».</p>
    </div>
</div>

<div class="grid grid-side-main">
    <section class="card" style="align-self:start">
        <div class="card__header"><h3>Nouvelle affectation</h3></div>
        <?php if ($categories === []): ?>
            <?= View::partiel('vide', [
                'icone' => 'tag', 'titre' => 'Aucune catégorie', 'texte' => 'Créez d\'abord une catégorie de frais.',
                'action' => '<a href="' . url('/categories/nouveau') . '" class="btn btn-primary">' . icone('plus') . ' Créer une catégorie</a>',
            ]) ?>
        <?php else: ?>
            <form method="post" action="<?= url('/frais/affecter') ?>" class="card__body" data-validate
                  data-confirm="Les frais seront créés pour tous les élèves des classes cochées (les doublons sont ignorés)."
                  data-confirm-titre="Confirmer l'affectation ?" data-confirm-bouton="Affecter" data-confirm-type="primary">
                <?= csrf_champ() ?>
                <?php
                $options = ['' => 'Choisir une catégorie…'];
                foreach ($categories as $c) {
                    $options[(int) $c['id_categorie']] = $c['libelle'] . ' — ' . formaterMontant($c['montant_defaut']);
                }
                ?>
                <?= View::partiel('champ', [
                    'nom' => 'id_categorie', 'libelle' => 'Catégorie de frais', 'type' => 'select', 'requis' => true, 'options' => $options,
                ]) ?>
                <div class="mt-2">
                    <?= View::partiel('champ', [
                        'nom' => 'echeance', 'libelle' => 'Échéance', 'type' => 'date', 'requis' => true,
                        'aide' => 'Date limite de paiement.',
                    ]) ?>
                </div>

                <fieldset class="field checks mt-2<?= $erreurClasses ? ' has-error' : '' ?>">
                    <legend class="label">Classes concernées<span class="required" aria-hidden="true">*</span></legend>
                    <label class="check check--all">
                        <input type="checkbox" data-check-all="classes[]"> <span>Toutes les classes</span>
                    </label>
                    <?php foreach ($classes as $cl): ?>
                        <label class="check">
                            <input type="checkbox" name="classes[]" value="<?= (int) $cl['id_classe'] ?>"<?= in_array((int) $cl['id_classe'], $classesCochees, true) ? ' checked' : '' ?>>
                            <span><?= e($cl['libelle']) ?></span>
                            <span class="check__meta"><?= (int) $cl['nb_eleves'] ?> élève<?= $cl['nb_eleves'] > 1 ? 's' : '' ?></span>
                        </label>
                    <?php endforeach; ?>
                    <?php if ($erreurClasses): ?><span class="error"><?= icone('alert-circle') ?><?= e($erreurClasses) ?></span><?php endif; ?>
                </fieldset>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-block"><?= icone('check') ?> Affecter les frais</button>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card__header">
            <h3>Affectations en cours</h3>
            <span class="text-small text-muted"><?= count($affectations) ?> affectation<?= count($affectations) > 1 ? 's' : '' ?></span>
        </div>
        <?php if ($affectations === []): ?>
            <?= View::partiel('vide', [
                'icone' => 'file-text', 'titre' => 'Aucun frais affecté',
                'texte' => 'Les affectations apparaîtront ici avec leur taux de recouvrement.',
            ]) ?>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table-stack">
                    <thead>
                        <tr>
                            <th>Frais</th>
                            <th>Classe</th>
                            <th>Échéance</th>
                            <th class="col-num">Dû</th>
                            <th>Recouvrement</th>
                            <th class="col-actions"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($affectations as $a): ?>
                            <?php $pct = pourcentagePaye($a['paye'], $a['du']); $echu = $a['echeance'] < date('Y-m-d'); ?>
                            <tr>
                                <td class="cell-main">
                                    <strong><?= e($a['categorie']) ?></strong>
                                    <span class="d-block text-small text-muted"><?= (int) $a['nb_eleves'] ?> élève<?= $a['nb_eleves'] > 1 ? 's' : '' ?> · <?= (int) $a['nb_payes'] ?> soldé<?= $a['nb_payes'] > 1 ? 's' : '' ?></span>
                                </td>
                                <td data-label="Classe"><?= e($a['classe']) ?></td>
                                <td data-label="Échéance" class="num">
                                    <?= e(formaterDate($a['echeance'])) ?>
                                    <?php if ($echu && $pct < 100): ?><span class="badge badge-impaye no-dot">Échue</span><?php endif; ?>
                                </td>
                                <td data-label="Dû" class="col-num num"><?= e(formaterMontant($a['du'])) ?></td>
                                <td data-label="Recouvrement">
                                    <div class="progress-line">
                                        <span class="progress"><span class="progress__bar<?= $pct >= 100 ? ' is-complete' : '' ?>" style="width: <?= $pct ?>%"></span></span>
                                        <span class="num text-small"><?= $pct ?> %</span>
                                    </div>
                                </td>
                                <td class="col-actions">
                                    <?php if ((int) $a['nb_paiements'] === 0): ?>
                                        <form method="post" action="<?= url('/frais/annuler') ?>"
                                              data-confirm="<?= e('Les ' . $a['nb_eleves'] . ' frais « ' . $a['categorie'] . ' » de ' . $a['classe'] . ' (échéance ' . formaterDate($a['echeance']) . ') seront supprimés.') ?>"
                                              data-confirm-titre="Annuler cette affectation ?" data-confirm-bouton="Annuler l'affectation">
                                            <?= csrf_champ() ?>
                                            <input type="hidden" name="id_categorie" value="<?= (int) $a['id_categorie'] ?>">
                                            <input type="hidden" name="id_classe" value="<?= (int) $a['id_classe'] ?>">
                                            <input type="hidden" name="echeance" value="<?= e($a['echeance']) ?>">
                                            <button type="submit" class="btn btn-ghost btn-icon btn-sm text-danger" data-no-loading title="Annuler l'affectation (aucun paiement)" aria-label="Annuler l'affectation"><?= icone('trash') ?></button>
                                        </form>
                                    <?php else: ?>
                                        <span class="btn btn-ghost btn-icon btn-sm is-disabled" title="Des paiements existent : annulation impossible"><?= icone('lock') ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
