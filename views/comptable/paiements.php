<?php
/**
 * Liste des paiements (tous modes et statuts).
 * @var array  $paiements
 * @var string $somme      total des paiements réussis selon les filtres
 * @var array  $filtres
 * @var array  $pagination
 */
$filtreActif = $filtres['q'] !== '' || $filtres['statut'] !== '' || $filtres['mode'] !== '' || $filtres['du'] !== '' || $filtres['au'] !== '';
$iconesMode = ['especes' => 'banknote', 'mobile_money' => 'smartphone', 'carte' => 'credit-card'];
?>
<div class="page-header">
    <div>
        <h2>Paiements</h2>
        <p><?= (int) $pagination['total'] ?> paiement<?= $pagination['total'] > 1 ? 's' : '' ?> · <strong class="num"><?= e(formaterMontant($somme)) ?></strong> encaissés (paiements réussis<?= $filtreActif ? ', selon les filtres' : '' ?>)</p>
    </div>
    <div class="page-header__actions">
        <a href="<?= url('/paiements/guichet') ?>" class="btn btn-primary"><?= icone('banknote') ?> Encaisser au guichet</a>
    </div>
</div>

<section class="card">
    <form method="get" action="<?= url('/paiements') ?>" class="toolbar" role="search">
        <div class="input-icon search">
            <?= icone('search') ?>
            <input type="search" name="q" value="<?= e($filtres['q']) ?>" placeholder="Référence, n° de reçu, matricule, élève…" aria-label="Rechercher">
        </div>
        <select name="statut" data-autosubmit aria-label="Statut">
            <option value="">Tous les statuts</option>
            <?php foreach (Paiement::STATUTS as $cle => $libelle): ?>
                <option value="<?= e($cle) ?>"<?= $filtres['statut'] === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="mode" data-autosubmit aria-label="Mode">
            <option value="">Tous les modes</option>
            <?php foreach (Paiement::MODES as $cle => $libelle): ?>
                <option value="<?= e($cle) ?>"<?= $filtres['mode'] === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="toolbar__field"><span class="text-small text-muted">Du</span><input type="date" name="du" value="<?= e($filtres['du']) ?>" data-autosubmit></label>
        <label class="toolbar__field"><span class="text-small text-muted">Au</span><input type="date" name="au" value="<?= e($filtres['au']) ?>" data-autosubmit></label>
        <button type="submit" class="btn btn-secondary" data-no-loading><?= icone('filter') ?> Filtrer</button>
        <?php if ($filtreActif): ?><a href="<?= url('/paiements') ?>" class="btn btn-ghost">Réinitialiser</a><?php endif; ?>
    </form>

    <?php if ($paiements === []): ?>
        <?= View::partiel('vide', [
            'icone'  => 'receipt',
            'titre'  => $filtreActif ? 'Aucun paiement ne correspond' : 'Aucun paiement pour l\'instant',
            'texte'  => $filtreActif ? 'Modifiez les filtres de recherche.' : 'Les paiements au guichet et en ligne apparaîtront ici.',
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table-stack">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Élève</th>
                        <th>Frais</th>
                        <th>Mode</th>
                        <th class="col-num">Montant</th>
                        <th>Statut</th>
                        <th class="col-actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paiements as $p): ?>
                        <?php $id = (int) $p['id_paiement']; ?>
                        <tr>
                            <td class="cell-main">
                                <a href="<?= url("/paiements/$id") ?>" class="fw-600 num"><?= e($p['reference']) ?></a>
                                <span class="d-block text-small text-muted"><?= e(formaterDateHeure($p['date_paiement'])) ?></span>
                            </td>
                            <td data-label="Élève">
                                <div>
                                    <?= e($p['eleve_nom'] . ' ' . $p['eleve_prenom']) ?>
                                    <span class="d-block text-small text-muted"><?= e($p['classe']) ?></span>
                                </div>
                            </td>
                            <td data-label="Frais"><?= e($p['categorie']) ?></td>
                            <td data-label="Mode"><span class="mode"><?= icone($iconesMode[$p['mode']], 'icon-sm') ?> <?= e(Paiement::MODES[$p['mode']]) ?></span></td>
                            <td data-label="Montant" class="col-num num fw-600"><div><?= e(formaterMontant($p['montant'])) ?><?php if (($p['devise_versee'] ?? DEVISE) !== DEVISE): ?><span class="d-block text-small text-muted num">versé <?= e(formaterMontant($p['montant_verse'], $p['devise_versee'])) ?></span><?php endif; ?></div></td>
                            <td data-label="Statut"><?= badgeStatut($p['statut']) ?></td>
                            <td class="col-actions">
                                <div class="actions">
                                    <?php if ($p['id_recu']): ?>
                                        <a href="<?= url('/recus/' . (int) $p['id_recu']) ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-icon btn-sm" title="Reçu <?= e($p['recu_numero']) ?>" aria-label="Ouvrir le reçu <?= e($p['recu_numero']) ?>"><?= icone('file-text') ?></a>
                                    <?php endif; ?>
                                    <a href="<?= url("/paiements/$id") ?>" class="btn btn-ghost btn-icon btn-sm" title="Détail" aria-label="Détail du paiement <?= e($p['reference']) ?>"><?= icone('eye') ?></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= View::partiel('pagination', ['pagination' => $pagination, 'libelle' => 'paiements']) ?>
</section>
