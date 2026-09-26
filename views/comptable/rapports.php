<?php
/**
 * Rapport d'encaissement d'une période.
 * @var array $periode       type, du, au, libelle, pas, date, mois
 * @var array $kpi           indicateurs (RapportController::indicateurs)
 * @var array $serie         points du graphique
 * @var array $parCategorie  ventilations : libelle, nb, somme
 * @var array $parClasse
 * @var array $parMode
 * @var array $impayesClasse libelle, nb, reste, eleves
 */
$parametres = array_filter([
    'type' => $periode['type'],
    'date' => $periode['type'] === 'jour' ? $periode['date'] : null,
    'mois' => $periode['type'] === 'mois' ? $periode['mois'] : null,
    'du'   => $periode['type'] === 'intervalle' ? $periode['du'] : null,
    'au'   => $periode['type'] === 'intervalle' ? $periode['au'] : null,
]);
$somme = (float) $kpi['somme'];
$ventilations = [
    ['Par catégorie de frais', 'tag', $parCategorie],
    ['Par classe', 'school', $parClasse],
    ['Par mode de paiement', 'wallet', $parMode],
];
?>
<div class="page-header">
    <div>
        <h2>Rapport d'encaissement</h2>
        <p><?= e($periode['libelle']) ?> · paiements réussis uniquement</p>
    </div>
    <div class="page-header__actions">
        <a href="<?= url('/rapports/pdf', $parametres) ?>" target="_blank" rel="noopener" class="btn btn-secondary"><?= icone('printer') ?> Aperçu PDF</a>
        <a href="<?= url('/rapports/pdf', $parametres + ['telecharger' => 1]) ?>" class="btn btn-primary"><?= icone('download') ?> Exporter en PDF</a>
    </div>
</div>

<form method="get" action="<?= url('/rapports') ?>" class="card toolbar mb-3 periode" data-periode>
    <div class="segmented" role="radiogroup" aria-label="Type de période">
        <?php foreach (['jour' => 'Jour', 'mois' => 'Mois', 'intervalle' => 'Intervalle'] as $cle => $libelle): ?>
            <label><input type="radio" name="type" value="<?= $cle ?>"<?= $periode['type'] === $cle ? ' checked' : '' ?>><span><?= $libelle ?></span></label>
        <?php endforeach; ?>
    </div>
    <label class="toolbar__field" data-si-periode="jour"><span class="text-small text-muted">Date</span><input type="date" name="date" value="<?= e($periode['date']) ?>"></label>
    <label class="toolbar__field" data-si-periode="mois"><span class="text-small text-muted">Mois</span><input type="month" name="mois" value="<?= e($periode['mois']) ?>"></label>
    <label class="toolbar__field" data-si-periode="intervalle"><span class="text-small text-muted">Du</span><input type="date" name="du" value="<?= e($periode['du']) ?>"></label>
    <label class="toolbar__field" data-si-periode="intervalle"><span class="text-small text-muted">Au</span><input type="date" name="au" value="<?= e($periode['au']) ?>"></label>
    <button type="submit" class="btn btn-primary" data-no-loading><?= icone('bar-chart') ?> Afficher</button>
</form>

<div class="grid grid-4 mb-3">
    <div class="card stat">
        <span class="stat__icon is-success"><?= icone('wallet') ?></span>
        <div>
            <p class="stat__label">Total encaissé</p>
            <p class="stat__value"><?= e(formaterMontant($kpi['somme'])) ?></p>
            <p class="stat__hint"><?= $kpi['nb'] ?> paiement<?= $kpi['nb'] > 1 ? 's' : '' ?> sur la période</p>
        </div>
    </div>
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
            <p class="stat__hint"><?= e(formaterMontant($kpi['paye_total'])) ?> sur <?= e(formaterMontant($kpi['du_total'])) ?></p>
        </div>
    </div>
    <div class="card stat">
        <span class="stat__icon is-info"><?= icone('smartphone') ?></span>
        <div>
            <p class="stat__label">Part des paiements en ligne</p>
            <p class="stat__value"><?= e(number_format($kpi['part_ligne'], 1, ',', ' ')) ?> %</p>
            <p class="stat__hint"><?= e(formaterMontant($kpi['somme_en_ligne'])) ?> · <?= $kpi['nb_en_ligne'] ?> paiement<?= $kpi['nb_en_ligne'] > 1 ? 's' : '' ?></p>
        </div>
    </div>
</div>

<section class="card mb-3">
    <div class="card__header">
        <h3>Encaissements <?= $periode['pas'] === 'mois' ? 'par mois' : 'par jour' ?></h3>
        <span class="text-small text-muted"><?= e($periode['libelle']) ?></span>
    </div>
    <div class="card__body">
        <?php if ($somme <= 0): ?>
            <?= View::partiel('vide', ['icone' => 'bar-chart', 'titre' => 'Aucun encaissement sur cette période', 'texte' => 'Choisissez une autre période ci-dessus.']) ?>
        <?php else: ?>
            <?= View::partiel('graphique_barres', ['points' => $serie, 'serie' => 'Encaissements']) ?>
        <?php endif; ?>
    </div>
</section>

<?php if ($somme > 0): ?>
    <div class="grid grid-3 mb-3">
        <?php foreach ($ventilations as [$titre, $icone, $lignes]): ?>
            <section class="card">
                <div class="card__header"><h3><?= e($titre) ?></h3></div>
                <table class="table ventilation">
                    <tbody>
                        <?php foreach ($lignes as $l): ?>
                            <?php $part = (float) $l['somme'] / $somme * 100; ?>
                            <tr>
                                <td>
                                    <?= e($l['libelle']) ?>
                                    <span class="ventilation__barre"><span style="width: <?= round($part, 1) ?>%"></span></span>
                                </td>
                                <td class="col-num num">
                                    <strong><?= e(formaterMontant($l['somme'])) ?></strong>
                                    <span class="d-block text-small text-muted"><?= e(number_format($part, 1, ',', ' ')) ?> % · <?= (int) $l['nb'] ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="card">
    <div class="card__header">
        <h3>Reste à recouvrer par classe (à ce jour)</h3>
        <a href="<?= url('/impayes') ?>" class="btn btn-ghost btn-sm">Liste des impayés <?= icone('arrow-right') ?></a>
    </div>
    <div class="table-wrap">
        <table class="table table-stack">
            <thead><tr><th>Classe</th><th class="col-num">Élèves concernés</th><th class="col-num">Frais non soldés</th><th class="col-num">Reste</th></tr></thead>
            <tbody>
                <?php foreach ($impayesClasse as $c): ?>
                    <tr>
                        <td class="cell-main"><strong><?= e($c['libelle']) ?></strong></td>
                        <td data-label="Élèves concernés" class="col-num num"><?= (int) $c['eleves'] ?></td>
                        <td data-label="Frais non soldés" class="col-num num"><?= (int) $c['nb'] ?></td>
                        <td data-label="Reste" class="col-num num fw-600"><?= e(formaterMontant($c['reste'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
