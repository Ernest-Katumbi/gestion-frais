<?php
/**
 * Rapport d'encaissement (PDF A4) — rendu par Dompdf.
 * @var array  $periode
 * @var array  $kpi
 * @var array  $serie
 * @var array  $parCategorie
 * @var array  $parClasse
 * @var array  $parMode
 * @var array  $impayesClasse
 * @var array  $comptable
 * @var string $logo
 */
$somme = (float) $kpi['somme'];
$max = max([0.0, ...array_column($serie, 'valeur')]);
$n = count($serie);
$pasEtiquette = $n <= 16 ? 1 : (int) ceil($n / 12);
$ventilations = ['Catégorie' => $parCategorie, 'Classe' => $parClasse, 'Mode' => $parMode];
$devises = $parDevise ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Rapport d'encaissement — <?= e($periode['libelle']) ?></title>
<style>
    @page { margin: 14mm 13mm 16mm; }
    * { font-family: "DejaVu Sans", sans-serif; }
    body { margin: 0; color: #0F172A; font-size: 8.4pt; line-height: 1.35; }
    table { border-collapse: collapse; width: 100%; }
    td, th { vertical-align: top; }
    .entete td { vertical-align: middle; }
    .logo { width: 58px; height: 58px; }
    .institut { font-size: 13pt; font-weight: bold; color: #062B6E; }
    .sous-titre { color: #64748B; font-size: 7.6pt; }
    .titre { text-align: right; }
    .titre .libelle { font-size: 7pt; letter-spacing: 1.3px; color: #64748B; text-transform: uppercase; }
    .titre .periode { font-size: 12pt; font-weight: bold; }
    .filet { height: 2.5px; background: #0541B6; margin: 7px 0 12px; }
    .kpi td { width: 25%; padding: 8px 10px; border: 1px solid #E2E8F0; background: #F8FAFC; }
    .kpi .l { color: #64748B; font-size: 7.2pt; text-transform: uppercase; letter-spacing: .5px; }
    .kpi .v { font-size: 13pt; font-weight: bold; color: #062B6E; }
    .kpi .h { color: #64748B; font-size: 7pt; }
    h2 { margin: 16px 0 6px; font-size: 8pt; letter-spacing: 1.1px; text-transform: uppercase; color: #0541B6; }
    .graph { table-layout: fixed; }
    .graph td { padding: 0 1px; vertical-align: bottom; text-align: center; overflow: hidden; }
    .graph .barre { background: #0541B6; margin: 0 auto; }
    .graph .etiquette td { padding-top: 3px; font-size: 6pt; color: #64748B; vertical-align: top; overflow: visible; white-space: nowrap; }
    .graph .zone td { height: 120px; border-bottom: 1px solid #94A3B8; }
    .liste th { padding: 5px 6px; background: #EAF0FC; color: #062B6E; font-size: 7.2pt; text-align: left; text-transform: uppercase; letter-spacing: .4px; }
    .liste td { padding: 4.5px 6px; border-bottom: 0.5px solid #E2E8F0; }
    .liste tr.total td { font-weight: bold; background: #F8FAFC; border-bottom: 0; }
    .num { text-align: right; white-space: nowrap; }
    .colonnes td.col { width: 33.33%; padding-right: 8px; }
    .vent { table-layout: fixed; font-size: 7.4pt; }
    .vent th { font-size: 6.4pt; }
    .vent .c1 { width: 50%; }
    .vent .c2 { width: 34%; }
    .vent .c3 { width: 16%; }
    .colonnes td.col:last-child { padding-right: 0; }
    .muted { color: #64748B; }
    .pied { position: fixed; bottom: -9mm; left: 0; right: 0; font-size: 6.6pt; color: #94A3B8; text-align: center; }
</style>
</head>
<body>

<div class="pied">
    <?= e(APP_NOM) ?> — Rapport généré le <?= e(formaterDateHeure(date('Y-m-d H:i:s'))) ?> par <?= e($comptable['nom']) ?> · seuls les paiements réussis sont comptés
</div>

<table class="entete">
    <tr>
        <td style="width: 66px"><img class="logo" src="<?= $logo ?>" alt=""></td>
        <td>
            <div class="institut"><?= e(APP_NOM) ?></div>
            <div class="sous-titre"><?= e(APP_MENTION) ?> · <?= e(APP_VILLE) ?></div>
        </td>
        <td class="titre">
            <div class="libelle">Rapport d'encaissement</div>
            <div class="periode"><?= e($periode['libelle']) ?></div>
            <div class="sous-titre">du <?= e(formaterDate($periode['du'])) ?> au <?= e(formaterDate($periode['au'])) ?></div>
        </td>
    </tr>
</table>
<div class="filet"></div>

<table class="kpi">
    <tr>
        <td><div class="l">Total encaissé</div><div class="v"><?= e(formaterMontant($kpi['somme'])) ?></div><div class="h"><?= $kpi['nb'] ?> paiement(s) sur la période</div></td>
        <td><div class="l">Total impayé</div><div class="v"><?= e(formaterMontant($kpi['reste'])) ?></div><div class="h">reste à recouvrer à ce jour</div></td>
        <td><div class="l">Taux de recouvrement</div><div class="v"><?= e(number_format($kpi['taux'], 1, ',', ' ')) ?> %</div><div class="h"><?= e(formaterMontant($kpi['paye_total'])) ?> / <?= e(formaterMontant($kpi['du_total'])) ?></div></td>
        <td><div class="l">Paiements en ligne</div><div class="v"><?= e(number_format($kpi['part_ligne'], 1, ',', ' ')) ?> %</div><div class="h"><?= e(formaterMontant($kpi['somme_en_ligne'])) ?> · <?= $kpi['nb_en_ligne'] ?> paiement(s)</div></td>
    </tr>
</table>

<h2>Encaissements <?= $periode['pas'] === 'mois' ? 'par mois' : 'par jour' ?> <span class="muted">(max. <?= e(formaterMontant($max)) ?>)</span></h2>
<?php if ($somme <= 0): ?>
    <p class="muted">Aucun encaissement sur cette période.</p>
<?php else: ?>
    <table class="graph">
        <tr class="zone">
            <?php foreach ($serie as $p): ?>
                <td style="width: <?= round(100 / max(1, $n), 3) ?>%"><div class="barre" style="width: 70%; height: <?= $max > 0 ? max($p['valeur'] > 0 ? 1 : 0, round($p['valeur'] / $max * 118)) : 0 ?>px"></div></td>
            <?php endforeach; ?>
        </tr>
        <tr class="etiquette">
            <?php foreach ($serie as $i => $p): ?>
                <td><?= $i % $pasEtiquette === 0 ? e($p['court']) : '' ?></td>
            <?php endforeach; ?>
        </tr>
    </table>
<?php endif; ?>

<?php if ($somme > 0): ?>
    <h2>Ventilation des encaissements</h2>
    <table class="colonnes">
        <tr>
            <?php foreach ($ventilations as $titre => $lignes): ?>
                <td class="col">
                    <table class="liste vent">
                        <tr><th class="c1"><?= e($titre) ?></th><th class="c2 num">Montant</th><th class="c3 num">%</th></tr>
                        <?php foreach ($lignes as $l): ?>
                            <tr>
                                <td><?= e($l['libelle']) ?></td>
                                <td class="num"><?= e(formaterMontant($l['somme'])) ?></td>
                                <td class="num"><?= e(number_format((float) $l['somme'] / $somme * 100, 1, ',', ' ')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total"><td>Total</td><td class="num"><?= e(formaterMontant($somme)) ?></td><td class="num">100</td></tr>
                    </table>
                </td>
            <?php endforeach; ?>
        </tr>
    </table>
<?php endif; ?>

<?php if ($somme > 0 && $devises !== []): ?>
    <h2>Encaissements par devise de versement</h2>
    <table class="liste">
        <tr><th>Devise versée</th><th class="num">Paiements</th><th class="num">Contre-valeur (<?= e(symboleDevise()) ?>)</th><th class="num">%</th></tr>
        <?php foreach ($devises as $l): ?>
            <tr>
                <td><?= e($l['libelle']) ?></td>
                <td class="num"><?= (int) $l['nb'] ?></td>
                <td class="num"><?= e(formaterMontant($l['somme'])) ?></td>
                <td class="num"><?= e(number_format((float) $l['somme'] / $somme * 100, 1, ',', ' ')) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<h2>Reste à recouvrer par classe (à ce jour)</h2>
<table class="liste">
    <tr><th>Classe</th><th class="num">Élèves concernés</th><th class="num">Frais non soldés</th><th class="num">Reste à recouvrer</th></tr>
    <?php $totalReste = 0.0; ?>
    <?php foreach ($impayesClasse as $c): ?>
        <?php $totalReste += (float) $c['reste']; ?>
        <tr>
            <td><?= e($c['libelle']) ?></td>
            <td class="num"><?= (int) $c['eleves'] ?></td>
            <td class="num"><?= (int) $c['nb'] ?></td>
            <td class="num"><?= e(formaterMontant($c['reste'])) ?></td>
        </tr>
    <?php endforeach; ?>
    <tr class="total"><td colspan="3">Total</td><td class="num"><?= e(formaterMontant($totalReste)) ?></td></tr>
</table>

</body>
</html>
