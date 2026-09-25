<?php
/**
 * Détail d'un frais pour le parent : situation, paiements et reçus.
 * @var array $f
 * @var array $paiements
 */
$id = (int) $f['id_frais'];
$echu = $f['statut'] !== 'paye' && $f['echeance'] < date('Y-m-d');
$urlPayer = '/parent/frais/' . $id . '/payer';
?>
<a href="<?= url('/parent') ?>" class="btn btn-ghost btn-sm mb-2"><?= icone('arrow-left') ?> Retour à l'accueil</a>

<section class="card mb-3">
    <div class="card__body">
        <div class="frais-card__head">
            <div>
                <p class="text-small text-muted mb-0"><?= e($f['eleve_prenom'] . ' ' . $f['eleve_nom']) ?> · <?= e($f['classe']) ?></p>
                <h2 class="mb-0"><?= e($f['categorie']) ?></h2>
            </div>
            <?= badgeStatut($f['statut']) ?>
        </div>
        <p class="frais-card__echeance mt-1<?= $echu ? ' text-danger' : '' ?>">
            <?= icone('calendar', 'icon-sm') ?>
            Échéance : <?= e(formaterDate($f['echeance'])) ?><?= $f['statut'] !== 'paye' ? ' · ' . e(libelleEcheance($f['echeance'])) : '' ?>
        </p>
        <div class="frais-card__montants frais-card__montants--lg">
            <div><span>Montant</span><strong class="num"><?= e(formaterMontant($f['montant'])) ?></strong></div>
            <div><span>Déjà payé</span><strong class="num text-success"><?= e(formaterMontant($f['montant_paye'])) ?></strong></div>
            <div><span>Reste à payer</span><strong class="num<?= (float) $f['reste'] > 0 ? ' text-danger' : '' ?>"><?= e(formaterMontant($f['reste'])) ?></strong></div>
        </div>
        <span class="progress"><span class="progress__bar<?= $f['statut'] === 'paye' ? ' is-complete' : '' ?>" style="width: <?= pourcentagePaye($f['montant_paye'], $f['montant']) ?>%"></span></span>

        <?php if ($f['statut'] !== 'paye'): ?>
            <div class="mt-2">
                <?php if (Router::instance()->existe($urlPayer)): ?>
                    <a href="<?= url($urlPayer) ?>" class="btn btn-primary btn-lg btn-block"><?= icone('smartphone') ?> Payer <?= e(formaterMontant($f['reste'])) ?></a>
                <?php else: ?>
                    <p class="text-small text-muted mb-0">Vous pouvez régler ce frais au guichet de l'Institut.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="card">
    <div class="card__header"><h3>Paiements et reçus</h3></div>
    <?php if ($paiements === []): ?>
        <?= View::partiel('vide', ['icone' => 'receipt', 'titre' => 'Aucun paiement pour ce frais', 'texte' => 'Chaque paiement confirmé donne lieu à un reçu téléchargeable ici.']) ?>
    <?php else: ?>
        <ul class="list">
            <?php foreach ($paiements as $p): ?>
                <li>
                    <div class="list__main">
                        <strong class="num"><?= e(formaterMontant($p['montant'])) ?></strong>
                        <span><?= e(formaterDateHeure($p['date_paiement'])) ?> · <?= e(Paiement::MODES[$p['mode']]) ?></span>
                    </div>
                    <?= badgeStatut($p['statut']) ?>
                    <?php if ($p['id_recu']): ?>
                        <a href="<?= url('/recus/' . (int) $p['id_recu'], ['telecharger' => 1]) ?>" class="btn btn-soft btn-sm" aria-label="Télécharger le reçu <?= e($p['recu_numero']) ?>"><?= icone('download') ?> Reçu</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
