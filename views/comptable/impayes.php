<?php
/**
 * Liste des impayés (frais impayés ou partiellement payés).
 * @var array $impayes
 * @var array $totaux     n, du, paye, reste, eleves
 * @var array $filtres
 * @var array $classes
 * @var array $categories
 * @var array $pagination
 */
$filtreActif = $filtres['classe'] || $filtres['categorie'] || $filtres['echeance_max'] !== '' || $filtres['echus'];
$aujourdhui = date('Y-m-d');
?>
<div class="page-header">
    <div>
        <h2>Impayés</h2>
        <p>Frais non soldés, du plus ancien au plus récent.</p>
    </div>
    <div class="page-header__actions">
        <a href="<?= url('/impayes/pdf', array_filter(['classe' => $filtres['classe'] ?: null, 'categorie' => $filtres['categorie'] ?: null, 'echeance_max' => $filtres['echeance_max'] ?: null, 'echus' => $filtres['echus'] ? 1 : null])) ?>" target="_blank" rel="noopener" class="btn btn-secondary"><?= icone('printer') ?> Exporter en PDF</a>
        <a href="<?= url('/paiements/guichet') ?>" class="btn btn-primary"><?= icone('banknote') ?> Encaisser un paiement</a>
    </div>
</div>

<div class="grid grid-3 mb-3">
    <div class="card stat">
        <span class="stat__icon is-danger"><?= icone('alert-circle') ?></span>
        <div>
            <p class="stat__label">Reste à recouvrer</p>
            <p class="stat__value"><?= e(formaterMontant($totaux['reste'])) ?></p>
            <p class="stat__hint">sur <?= e(formaterMontant($totaux['du'])) ?> dus</p>
        </div>
    </div>
    <div class="card stat">
        <span class="stat__icon is-warning"><?= icone('file-text') ?></span>
        <div>
            <p class="stat__label">Frais non soldés</p>
            <p class="stat__value"><?= (int) $totaux['n'] ?></p>
            <p class="stat__hint">dont <?= e(formaterMontant($totaux['paye'])) ?> déjà versés</p>
        </div>
    </div>
    <div class="card stat">
        <span class="stat__icon is-info"><?= icone('users') ?></span>
        <div>
            <p class="stat__label">Élèves concernés</p>
            <p class="stat__value"><?= (int) $totaux['eleves'] ?></p>
            <p class="stat__hint"><?= $filtreActif ? 'selon les filtres' : 'toutes classes confondues' ?></p>
        </div>
    </div>
</div>

<section class="card">
    <form method="get" action="<?= url('/impayes') ?>" class="toolbar">
        <select name="classe" data-autosubmit aria-label="Classe">
            <option value="0">Toutes les classes</option>
            <?php foreach ($classes as $id => $libelle): ?>
                <option value="<?= (int) $id ?>"<?= $filtres['classe'] === (int) $id ? ' selected' : '' ?>><?= e($libelle) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="categorie" data-autosubmit aria-label="Catégorie">
            <option value="0">Toutes les catégories</option>
            <?php foreach ($categories as $id => $libelle): ?>
                <option value="<?= (int) $id ?>"<?= $filtres['categorie'] === (int) $id ? ' selected' : '' ?>><?= e($libelle) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="toolbar__field">
            <span class="text-small text-muted">Échéance jusqu'au</span>
            <input type="date" name="echeance_max" value="<?= e($filtres['echeance_max']) ?>" data-autosubmit>
        </label>
        <label class="check check--inline">
            <input type="checkbox" name="echus" value="1"<?= $filtres['echus'] ? ' checked' : '' ?> data-autosubmit>
            <span>Échéance dépassée</span>
        </label>
        <?php if ($filtreActif): ?><a href="<?= url('/impayes') ?>" class="btn btn-ghost">Réinitialiser</a><?php endif; ?>
    </form>

    <?php if ($impayes === []): ?>
        <?= View::partiel('vide', [
            'icone' => 'check-circle',
            'titre' => $filtreActif ? 'Aucun impayé pour ces critères' : 'Aucun impayé',
            'texte' => $filtreActif ? 'Tous les frais correspondant aux filtres sont soldés.' : 'Tous les frais affectés sont soldés. Bravo !',
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table-stack">
                <thead>
                    <tr>
                        <th>Élève</th>
                        <th>Frais</th>
                        <th>Échéance</th>
                        <th class="col-num">Montant</th>
                        <th class="col-num">Payé</th>
                        <th class="col-num">Reste</th>
                        <th>Statut</th>
                        <th class="col-actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($impayes as $f): ?>
                        <?php $echu = $f['echeance'] < $aujourdhui; ?>
                        <tr>
                            <td class="cell-main">
                                <strong><?= e($f['eleve_nom'] . ' ' . $f['eleve_prenom']) ?></strong>
                                <span class="d-block text-small text-muted"><?= e($f['matricule']) ?> · <?= e($f['classe']) ?></span>
                                <span class="d-block text-small text-muted"><?= e($f['parent_nom']) ?><?= $f['parent_telephone'] ? ' · ' . e($f['parent_telephone']) : '' ?></span>
                            </td>
                            <td data-label="Frais"><?= e($f['categorie']) ?></td>
                            <td data-label="Échéance" class="num">
                                <div>
                                    <?= e(formaterDate($f['echeance'])) ?>
                                    <span class="d-block text-small <?= $echu ? 'text-danger' : 'text-muted' ?>"><?= e(libelleEcheance($f['echeance'])) ?></span>
                                </div>
                            </td>
                            <td data-label="Montant" class="col-num num"><?= e(formaterMontant($f['montant'])) ?></td>
                            <td data-label="Payé" class="col-num num"><?= e(formaterMontant($f['montant_paye'])) ?></td>
                            <td data-label="Reste" class="col-num num fw-600"><?= e(formaterMontant($f['reste'])) ?></td>
                            <td data-label="Statut"><?= badgeStatut($f['statut']) ?></td>
                            <td class="col-actions">
                                <a href="<?= url('/paiements/guichet', ['frais' => (int) $f['id_frais']]) ?>" class="btn btn-soft btn-sm"><?= icone('banknote') ?> Encaisser</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= View::partiel('pagination', ['pagination' => $pagination, 'libelle' => 'frais non soldés']) ?>
</section>
