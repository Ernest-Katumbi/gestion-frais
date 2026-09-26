<?php
/**
 * Accueil de l'espace parent : situation globale puis frais de chaque enfant (en cartes).
 * @var array $utilisateur
 * @var array $enfants     élèves avec 'frais' et 'totaux'
 * @var array $totaux      du, paye, reste (tous enfants)
 * @var int   $enRetard    nombre de frais échus non soldés
 * @var array $derniers    derniers paiements réussis
 */
$routeur = Router::instance();
?>
<section class="card welcome mb-3">
    <div>
        <h2>Bonjour, <?= e($utilisateur['nom']) ?></h2>
        <p>Consultez les frais scolaires de vos enfants et payez en toute sécurité.</p>
    </div>
    <img class="welcome__art" src="<?= asset('img/logo-institut-blanc.png') ?>" alt="">
</section>

<?php if ($enfants === []): ?>
    <section class="card">
        <?= View::partiel('vide', [
            'icone' => 'graduation-cap',
            'titre' => 'Aucun enfant rattaché pour l\'instant',
            'texte' => 'Dès que l\'Institut aura inscrit vos enfants, leurs frais scolaires apparaîtront ici.',
        ]) ?>
    </section>
<?php else: ?>
    <section class="card resume mb-3">
        <div class="resume__item">
            <span>Total à payer</span>
            <strong class="num"><?= e(formaterMontant($totaux['du'])) ?></strong>
        </div>
        <div class="resume__item">
            <span>Déjà payé</span>
            <strong class="num text-success"><?= e(formaterMontant($totaux['paye'])) ?></strong>
        </div>
        <div class="resume__item">
            <span>Reste à payer</span>
            <strong class="num<?= $totaux['reste'] > 0 ? ' text-danger' : '' ?>"><?= e(formaterMontant($totaux['reste'])) ?></strong>
        </div>
        <div class="resume__progress">
            <span class="progress"><span class="progress__bar<?= $totaux['reste'] <= 0 ? ' is-complete' : '' ?>" style="width: <?= pourcentagePaye($totaux['paye'], $totaux['du']) ?>%"></span></span>
            <span class="text-small text-muted"><?= pourcentagePaye($totaux['paye'], $totaux['du']) ?> % réglé</span>
        </div>
    </section>

    <?php if ($enRetard > 0): ?>
        <div class="alert alert-warning mb-3" role="status">
            <?= icone('alert-triangle') ?>
            <div><strong>
                <?= $enRetard ?> frais <?= $enRetard > 1 ? 'ont dépassé leur' : 'a dépassé sa' ?> date limite.
            </strong>Merci de régulariser dès que possible, en ligne ou au guichet de l'Institut.</div>
        </div>
    <?php endif; ?>

    <?php foreach ($enfants as $enfant): ?>
        <section class="enfant mb-3">
            <header class="enfant__head">
                <span class="avatar"><?= e(initiales($enfant['prenom'] . ' ' . $enfant['nom'])) ?></span>
                <div>
                    <h2><?= e($enfant['prenom'] . ' ' . $enfant['nom']) ?></h2>
                    <span class="text-small text-muted"><?= e($enfant['classe']) ?> · <?= e($enfant['matricule']) ?></span>
                </div>
                <span class="enfant__reste">
                    <?php if ($enfant['totaux']['reste'] > 0): ?>
                        Reste <strong class="num"><?= e(formaterMontant($enfant['totaux']['reste'])) ?></strong>
                    <?php elseif ($enfant['frais'] !== []): ?>
                        <span class="badge badge-paye">Tout est réglé</span>
                    <?php endif; ?>
                </span>
            </header>

            <?php if ($enfant['frais'] === []): ?>
                <div class="card"><p class="card__body text-muted mb-0">Aucun frais n'a encore été affecté à <?= e($enfant['prenom']) ?>.</p></div>
            <?php else: ?>
                <div class="frais-grid">
                    <?php foreach ($enfant['frais'] as $f): ?>
                        <?php
                        $id = (int) $f['id_frais'];
                        $echu = $f['statut'] !== 'paye' && $f['echeance'] < date('Y-m-d');
                        $urlPayer = '/parent/frais/' . $id . '/payer';
                        ?>
                        <article class="card frais-card<?= $f['statut'] === 'paye' ? ' is-paye' : '' ?><?= $echu ? ' is-echu' : '' ?>">
                            <div class="frais-card__head">
                                <h3><?= e($f['categorie']) ?></h3>
                                <?= badgeStatut($f['statut']) ?>
                            </div>
                            <p class="frais-card__echeance<?= $echu ? ' text-danger' : '' ?>">
                                <?= icone('calendar', 'icon-sm') ?>
                                <?= $f['statut'] === 'paye'
                                    ? 'Échéance du ' . e(formaterDate($f['echeance']))
                                    : 'À payer avant le ' . e(formaterDate($f['echeance'])) . ' · ' . e(libelleEcheance($f['echeance'])) ?>
                            </p>
                            <div class="frais-card__montants">
                                <div><span>Montant</span><strong class="num"><?= e(formaterMontant($f['montant'])) ?></strong></div>
                                <div><span>Payé</span><strong class="num"><?= e(formaterMontant($f['montant_paye'])) ?></strong></div>
                                <div><span>Reste</span><strong class="num"><?= e(formaterMontant($f['reste'])) ?></strong></div>
                            </div>
                            <span class="progress"><span class="progress__bar<?= $f['statut'] === 'paye' ? ' is-complete' : '' ?>" style="width: <?= pourcentagePaye($f['montant_paye'], $f['montant']) ?>%"></span></span>
                            <div class="frais-card__actions">
                                <a href="<?= url('/parent/frais/' . $id) ?>" class="btn btn-secondary btn-sm"><?= icone('eye') ?> Détails et reçus</a>
                                <?php if ($f['statut'] !== 'paye' && $routeur->existe($urlPayer)): ?>
                                    <a href="<?= url($urlPayer) ?>" class="btn btn-primary btn-sm"><?= icone('smartphone') ?> Payer</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <section class="card">
        <div class="card__header"><h3>Derniers paiements</h3></div>
        <?php if ($derniers === []): ?>
            <?= View::partiel('vide', ['icone' => 'receipt', 'titre' => 'Aucun paiement pour l\'instant', 'texte' => 'Vos reçus seront disponibles ici après chaque paiement.']) ?>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($derniers as $p): ?>
                    <li>
                        <span class="stat__icon stat__icon--sm is-success"><?= icone('check') ?></span>
                        <div class="list__main">
                            <strong><?= e($p['categorie']) ?> · <?= e($p['eleve_prenom']) ?></strong>
                            <span><?= e(formaterDate($p['date_paiement'])) ?> · <?= e(formaterMontant($p['montant'])) ?></span>
                        </div>
                        <?php if ($p['id_recu']): ?>
                            <a href="<?= url('/recus/' . (int) $p['id_recu'], ['telecharger' => 1]) ?>" class="btn btn-ghost btn-sm" aria-label="Télécharger le reçu <?= e($p['recu_numero']) ?>"><?= icone('download') ?> Reçu</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?php endif; ?>
