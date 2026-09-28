<?php
/**
 * Historique des paiements du parent (tous ses enfants, tous statuts).
 * @var array $paiements
 * @var array $enfants
 * @var int   $idEnfant  filtre (0 = tous)
 * @var float $total     somme des paiements réussis
 * @var int   $nbReussis
 */
$iconesMode = ['especes' => 'banknote', 'mobile_money' => 'smartphone', 'carte' => 'credit-card'];
?>
<div class="page-header">
    <div>
        <h2>Historique des paiements</h2>
        <p><?= $nbReussis ?> paiement<?= $nbReussis > 1 ? 's' : '' ?> réussi<?= $nbReussis > 1 ? 's' : '' ?> · <strong class="num"><?= e(formaterMontant($total)) ?></strong> versés</p>
    </div>
</div>

<section class="card">
    <?php if (count($enfants) > 1): ?>
        <form method="get" action="<?= url('/parent/historique') ?>" class="toolbar">
            <div class="segmented" role="radiogroup" aria-label="Enfant">
                <label><input type="radio" name="enfant" value="0" data-autosubmit<?= $idEnfant === 0 ? ' checked' : '' ?>><span>Tous</span></label>
                <?php foreach ($enfants as $en): ?>
                    <label><input type="radio" name="enfant" value="<?= (int) $en['id_eleve'] ?>" data-autosubmit<?= $idEnfant === (int) $en['id_eleve'] ? ' checked' : '' ?>><span><?= e($en['prenom']) ?></span></label>
                <?php endforeach; ?>
            </div>
            <noscript><button type="submit" class="btn btn-secondary">Filtrer</button></noscript>
        </form>
    <?php endif; ?>

    <?php if ($paiements === []): ?>
        <?= View::partiel('vide', [
            'icone' => 'receipt', 'titre' => 'Aucun paiement pour l\'instant',
            'texte' => 'Vos paiements, en ligne ou au guichet, apparaîtront ici avec leurs reçus.',
        ]) ?>
    <?php else: ?>
        <ul class="list historique">
            <?php foreach ($paiements as $p): ?>
                <li>
                    <span class="stat__icon stat__icon--sm<?= $p['statut'] === 'reussi' ? ' is-success' : ($p['statut'] === 'echoue' ? ' is-danger' : ' is-info') ?>"><?= icone($iconesMode[$p['mode']]) ?></span>
                    <div class="list__main">
                        <strong><?= e($p['categorie']) ?> · <?= e($p['eleve_prenom']) ?></strong>
                        <span><?= e(formaterDateHeure($p['date_paiement'])) ?> · <?= e(Paiement::MODES[$p['mode']]) ?></span>
                        <span class="num">Réf. <?= e($p['reference']) ?><?= $p['recu_numero'] ? ' · reçu ' . e($p['recu_numero']) : '' ?></span>
                    </div>
                    <div class="historique__droite">
                        <strong class="num d-block"><?= e(formaterMontant($p['montant'])) ?></strong>
                        <?php if (($p['devise_versee'] ?? DEVISE) !== DEVISE): ?><span class="d-block text-small text-muted num">versé <?= e(formaterMontant($p['montant_verse'], $p['devise_versee'])) ?></span><?php endif; ?>
                        <?= badgeStatut($p['statut']) ?>
                        <?php if ($p['id_recu']): ?>
                            <a href="<?= url('/recus/' . (int) $p['id_recu'], ['telecharger' => 1]) ?>" class="btn btn-soft btn-sm" aria-label="Télécharger le reçu <?= e($p['recu_numero']) ?>"><?= icone('download') ?> Reçu</a>
                        <?php elseif ($p['statut'] === 'en_attente'): ?>
                            <a href="<?= url('/parent/paiements/' . (int) $p['id_paiement']) ?>" class="btn btn-ghost btn-sm">Suivre</a>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
