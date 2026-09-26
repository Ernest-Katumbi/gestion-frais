<?php
/**
 * Tableau de bord du comptable.
 * @var array $utilisateur
 * @var array $kpi         indicateurs du mois en cours (RapportController::indicateurs)
 * @var array $serie       encaissements des 14 derniers jours
 * @var array $duJour      n, somme
 * @var array $recents
 * @var array $echeances   échéances des 14 prochains jours
 */
$iconesMode = ['especes' => 'banknote', 'mobile_money' => 'smartphone', 'carte' => 'credit-card'];
?>
<section class="card welcome mb-3">
    <div>
        <h2>Bonjour, <?= e($utilisateur['nom']) ?></h2>
        <p>Aujourd'hui : <?= (int) $duJour['n'] ?> encaissement<?= $duJour['n'] > 1 ? 's' : '' ?> pour <?= e(formaterMontant($duJour['somme'])) ?>.</p>
    </div>
    <img class="welcome__art" src="<?= asset('img/logo-institut-blanc.png') ?>" alt="">
</section>

<div class="grid grid-4 mb-3">
    <a class="card stat stat-link" href="<?= url('/rapports') ?>">
        <span class="stat__icon is-success"><?= icone('wallet') ?></span>
        <div>
            <p class="stat__label">Encaissé ce mois-ci</p>
            <p class="stat__value"><?= e(formaterMontant($kpi['somme'])) ?></p>
            <p class="stat__hint"><?= $kpi['nb'] ?> paiement<?= $kpi['nb'] > 1 ? 's' : '' ?> réussi<?= $kpi['nb'] > 1 ? 's' : '' ?></p>
        </div>
    </a>
    <a class="card stat stat-link" href="<?= url('/impayes') ?>">
        <span class="stat__icon is-danger"><?= icone('alert-circle') ?></span>
        <div>
            <p class="stat__label">Total impayé</p>
            <p class="stat__value"><?= e(formaterMontant($kpi['reste'])) ?></p>
            <p class="stat__hint">reste à recouvrer à ce jour</p>
        </div>
    </a>
    <div class="card stat">
        <span class="stat__icon"><?= icone('percent') ?></span>
        <div>
            <p class="stat__label">Taux de recouvrement</p>
            <p class="stat__value"><?= e(number_format($kpi['taux'], 1, ',', ' ')) ?> %</p>
            <span class="progress mt-1"><span class="progress__bar is-primaire" style="width: <?= min(100, round($kpi['taux'])) ?>%"></span></span>
        </div>
    </div>
    <div class="card stat">
        <span class="stat__icon is-info"><?= icone('smartphone') ?></span>
        <div>
            <p class="stat__label">Part des paiements en ligne</p>
            <p class="stat__value"><?= e(number_format($kpi['part_ligne'], 1, ',', ' ')) ?> %</p>
            <p class="stat__hint">du montant encaissé ce mois-ci</p>
        </div>
    </div>
</div>

<?php if ($kpi['echus'] > 0): ?>
    <a class="alert alert-warning mb-3 alert-lien" href="<?= url('/impayes', ['echus' => 1]) ?>">
        <?= icone('clock') ?>
        <div><strong><?= $kpi['echus'] ?> frais échu<?= $kpi['echus'] > 1 ? 's' : '' ?> non soldé<?= $kpi['echus'] > 1 ? 's' : '' ?></strong>
            <?= e(formaterMontant($kpi['reste_echu'])) ?> en retard de paiement. Voir la liste des impayés échus.</div>
    </a>
<?php endif; ?>

<section class="card mb-3">
    <div class="card__header">
        <h3>Encaissements des 14 derniers jours</h3>
        <a href="<?= url('/rapports') ?>" class="btn btn-ghost btn-sm">Rapports détaillés <?= icone('arrow-right') ?></a>
    </div>
    <div class="card__body">
        <?= View::partiel('graphique_barres', ['points' => $serie, 'serie' => 'Encaissements', 'tableau' => false]) ?>
    </div>
</section>

<div class="grid grid-main-side">
    <section class="card">
        <div class="card__header">
            <h3>Derniers paiements</h3>
            <a href="<?= url('/paiements') ?>" class="btn btn-ghost btn-sm">Tout voir <?= icone('arrow-right') ?></a>
        </div>
        <?php if ($recents === []): ?>
            <?= View::partiel('vide', [
                'icone' => 'receipt', 'titre' => 'Aucun paiement pour l\'instant',
                'texte' => 'Les encaissements au guichet et en ligne s\'afficheront ici.',
                'action' => '<a href="' . url('/paiements/guichet') . '" class="btn btn-primary">' . icone('banknote') . ' Encaisser un paiement</a>',
            ]) ?>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($recents as $p): ?>
                    <li>
                        <span class="stat__icon stat__icon--sm"><?= icone($iconesMode[$p['mode']]) ?></span>
                        <a class="list__main" href="<?= url('/paiements/' . (int) $p['id_paiement']) ?>">
                            <strong><?= e($p['eleve_prenom'] . ' ' . $p['eleve_nom']) ?> · <?= e($p['categorie']) ?></strong>
                            <span><?= e(formaterDateHeure($p['date_paiement'])) ?> · <?= e(Paiement::MODES[$p['mode']]) ?></span>
                        </a>
                        <div class="text-right">
                            <strong class="num d-block"><?= e(formaterMontant($p['montant'])) ?></strong>
                            <?= badgeStatut($p['statut']) ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <aside class="grid" style="align-content:start">
        <section class="card">
            <div class="card__header"><h3>Actions rapides</h3></div>
            <div class="card__body grid">
                <a class="btn btn-primary btn-block" href="<?= url('/paiements/guichet') ?>"><?= icone('banknote') ?> Encaisser au guichet</a>
                <a class="btn btn-secondary btn-block" href="<?= url('/frais') ?>"><?= icone('file-text') ?> Affecter des frais</a>
                <a class="btn btn-secondary btn-block" href="<?= url('/impayes') ?>"><?= icone('alert-circle') ?> Voir les impayés</a>
            </div>
        </section>
        <section class="card">
            <div class="card__header"><h3>Échéances à venir (14 jours)</h3></div>
            <?php if ($echeances === []): ?>
                <p class="card__body text-muted text-small mb-0">Aucune échéance de frais non soldés dans les deux prochaines semaines.</p>
            <?php else: ?>
                <ul class="list">
                    <?php foreach ($echeances as $ec): ?>
                        <li>
                            <span class="date-chip"><b><?= e((new DateTimeImmutable($ec['echeance']))->format('d')) ?></b><?= e(['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'][(int) (new DateTimeImmutable($ec['echeance']))->format('n')]) ?></span>
                            <div class="list__main">
                                <strong><?= e($ec['categorie']) ?></strong>
                                <span><?= (int) $ec['nb'] ?> frais non soldé<?= $ec['nb'] > 1 ? 's' : '' ?> · <?= e(libelleEcheance($ec['echeance'])) ?></span>
                            </div>
                            <strong class="num"><?= e(formaterMontant($ec['reste'])) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </aside>
</div>
