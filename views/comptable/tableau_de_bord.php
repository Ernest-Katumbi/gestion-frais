<?php
/**
 * Tableau de bord du comptable.
 * @var array $utilisateur
 * @var array $indicateurs du, paye, reste, echus, reste_echu
 * @var float $taux        taux de recouvrement (%)
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
    <img class="welcome__art" src="<?= asset('img/logo.svg') ?>" alt="">
</section>

<div class="grid grid-4 mb-3">
    <a class="card stat stat-link" href="<?= url('/paiements', ['statut' => 'reussi']) ?>">
        <span class="stat__icon is-success"><?= icone('wallet') ?></span>
        <div>
            <p class="stat__label">Total encaissé</p>
            <p class="stat__value"><?= e(formaterMontant($indicateurs['paye'])) ?></p>
            <p class="stat__hint">sur <?= e(formaterMontant($indicateurs['du'])) ?> facturés</p>
        </div>
    </a>
    <a class="card stat stat-link" href="<?= url('/impayes') ?>">
        <span class="stat__icon is-danger"><?= icone('alert-circle') ?></span>
        <div>
            <p class="stat__label">Reste à recouvrer</p>
            <p class="stat__value"><?= e(formaterMontant($indicateurs['reste'])) ?></p>
            <p class="stat__hint">tous frais non soldés</p>
        </div>
    </a>
    <div class="card stat">
        <span class="stat__icon"><?= icone('percent') ?></span>
        <div>
            <p class="stat__label">Taux de recouvrement</p>
            <p class="stat__value"><?= e(number_format($taux, 1, ',', ' ')) ?> %</p>
            <span class="progress mt-1"><span class="progress__bar" style="width: <?= min(100, round($taux)) ?>%"></span></span>
        </div>
    </div>
    <a class="card stat stat-link" href="<?= url('/impayes', ['echus' => 1]) ?>">
        <span class="stat__icon is-warning"><?= icone('clock') ?></span>
        <div>
            <p class="stat__label">Frais échus non soldés</p>
            <p class="stat__value"><?= (int) $indicateurs['echus'] ?></p>
            <p class="stat__hint"><?= e(formaterMontant($indicateurs['reste_echu'])) ?> en retard</p>
        </div>
    </a>
</div>

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
