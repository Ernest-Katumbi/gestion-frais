<?php
/**
 * Détail d'un paiement, avec son reçu et son QR code de vérification.
 * @var array       $p
 * @var string|null $qr
 * @var array       $autres  autres paiements sur le même frais
 */
$reste = (float) $p['frais_reste'];
$bandeaux = [
    'reussi'     => ['is-success', 'check-circle', 'Paiement réussi'],
    'en_attente' => ['is-info', 'clock', 'En attente de confirmation par la passerelle'],
    'echoue'     => ['is-danger', 'alert-circle', 'Paiement échoué'],
];
[$classe, $icone, $libelle] = $bandeaux[$p['statut']];
?>
<div class="page-header">
    <div>
        <h2>Paiement <span class="num"><?= e($p['reference']) ?></span></h2>
        <p><?= e(formaterDateHeure($p['date_paiement'])) ?> · <?= e(Paiement::MODES[$p['mode']]) ?></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= url('/paiements/guichet') ?>" class="btn btn-secondary"><?= icone('plus') ?> Nouvel encaissement</a>
        <?php if ($p['id_recu']): ?>
            <a href="<?= url('/recus/' . (int) $p['id_recu']) ?>" target="_blank" rel="noopener" class="btn btn-secondary"><?= icone('printer') ?> Imprimer</a>
            <a href="<?= url('/recus/' . (int) $p['id_recu'], ['telecharger' => 1]) ?>" class="btn btn-primary"><?= icone('download') ?> Télécharger le reçu</a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-main-side">
    <section class="card">
        <div class="status-banner <?= $classe ?>">
            <?= icone($icone) ?>
            <div>
                <strong><?= e($libelle) ?></strong>
                <span class="num"><?= e(formaterMontant($p['montant'])) ?></span>
            </div>
        </div>
        <div class="card__body">
            <dl class="dl">
                <dt>Élève</dt><dd><?= e($p['eleve_prenom'] . ' ' . $p['eleve_nom']) ?> <span class="text-muted">(<?= e($p['matricule']) ?>)</span></dd>
                <dt>Classe</dt><dd><?= e($p['classe']) ?></dd>
                <dt>Parent</dt><dd><?= e($p['parent_nom']) ?><?= $p['parent_telephone'] ? ' · <span class="num">' . e($p['parent_telephone']) . '</span>' : '' ?></dd>
                <dt>Frais</dt><dd><?= e($p['categorie']) ?> — échéance <?= e(formaterDate($p['echeance'])) ?></dd>
                <dt>Mode</dt><dd><?= e(Paiement::MODES[$p['mode']]) ?></dd>
                <?php if ($p['devise_versee'] !== DEVISE): ?>
                    <dt>Montant versé</dt><dd class="num"><?= e(formaterMontant($p['montant_verse'], $p['devise_versee'])) ?></dd>
                    <dt>Taux appliqué</dt><dd class="num"><?= e(formaterTaux($p['taux_applique'], $p['devise_versee'])) ?></dd>
                <?php endif; ?>
                <dt>Référence</dt><dd class="num"><?= e($p['reference']) ?></dd>
                <?php if ($p['comptable_nom']): ?><dt>Encaissé par</dt><dd><?= e($p['comptable_nom']) ?></dd><?php endif; ?>
            </dl>
            <hr>
            <h3 class="mb-1">Situation actuelle du frais</h3>
            <div class="progress-line mb-1">
                <span class="progress"><span class="progress__bar<?= $reste <= 0 ? ' is-complete' : '' ?>" style="width: <?= pourcentagePaye($p['frais_paye'], $p['frais_montant']) ?>%"></span></span>
                <?= badgeStatut($p['frais_statut']) ?>
            </div>
            <div class="mini-totaux">
                <div><span>Montant</span><strong class="num"><?= e(formaterMontant($p['frais_montant'])) ?></strong></div>
                <div><span>Payé</span><strong class="num"><?= e(formaterMontant($p['frais_paye'])) ?></strong></div>
                <div><span>Reste</span><strong class="num"><?= e(formaterMontant($reste)) ?></strong></div>
            </div>
        </div>
    </section>

    <aside class="grid" style="align-content:start">
        <section class="card">
            <div class="card__header"><h3>Reçu</h3></div>
            <?php if ($p['id_recu']): ?>
                <div class="card__body text-center">
                    <p class="fw-600 num mb-1"><?= e($p['recu_numero']) ?></p>
                    <img src="<?= e($qr) ?>" alt="QR code de vérification du reçu" class="qr-image" width="164" height="164">
                    <p class="text-small text-muted mt-1 mb-2">Le QR code renvoie vers la page publique de vérification.</p>
                    <a href="<?= e($p['code_qr']) ?>" target="_blank" rel="noopener" class="btn btn-soft btn-sm"><?= icone('shield-check') ?> Vérifier ce reçu</a>
                </div>
            <?php else: ?>
                <p class="card__body text-muted text-small mb-0">Aucun reçu : un reçu n'est émis que pour un paiement réussi.</p>
            <?php endif; ?>
        </section>

        <?php if ($autres !== []): ?>
            <section class="card">
                <div class="card__header"><h3>Autres paiements sur ce frais</h3></div>
                <ul class="list">
                    <?php foreach ($autres as $a): ?>
                        <li>
                            <a class="list__main" href="<?= url('/paiements/' . (int) $a['id_paiement']) ?>">
                                <strong class="num"><?= e(Monnaie::libelleVersement($a)) ?></strong>
                                <span><?= e(formaterDateHeure($a['date_paiement'])) ?> · <?= e(Paiement::MODES[$a['mode']]) ?></span>
                            </a>
                            <?= badgeStatut($a['statut']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </aside>
</div>
