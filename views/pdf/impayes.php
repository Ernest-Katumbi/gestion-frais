<?php
/**
 * Liste des impayés (PDF A4 paysage).
 * @var array  $lignes
 * @var array  $totaux   du, paye, reste
 * @var array  $criteres filtres appliqués (libellés)
 * @var array  $comptable
 * @var string $logo
 */
$aujourdhui = date('Y-m-d');
$statuts = Frais::STATUTS;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Liste des impayés</title>
<style>
    @page { margin: 12mm 12mm 15mm; }
    * { font-family: "DejaVu Sans", sans-serif; }
    body { margin: 0; color: #0F172A; font-size: 7.8pt; line-height: 1.3; }
    table { border-collapse: collapse; width: 100%; }
    .entete td { vertical-align: middle; }
    .logo { width: 50px; height: 50px; }
    .institut { font-size: 12pt; font-weight: bold; color: #062B6E; }
    .sous-titre { color: #64748B; font-size: 7.2pt; }
    .titre { text-align: right; }
    .titre .grand { font-size: 12pt; font-weight: bold; }
    .filet { height: 2.5px; background: #0541B6; margin: 6px 0 10px; }
    .liste th { padding: 5px; background: #EAF0FC; color: #062B6E; font-size: 6.8pt; text-align: left; text-transform: uppercase; }
    .liste td { padding: 4px 5px; border-bottom: 0.5px solid #E2E8F0; vertical-align: top; }
    .liste tr:nth-child(even) td { background: #FBFCFE; }
    .liste tr.total td { font-weight: bold; background: #F1F5F9; }
    .num { text-align: right; white-space: nowrap; }
    .muted { color: #64748B; }
    .retard { color: #B91C1C; }
    .pied { position: fixed; bottom: -9mm; left: 0; right: 0; font-size: 6.4pt; color: #94A3B8; text-align: center; }
</style>
</head>
<body>
<div class="pied"><?= e(APP_NOM) ?> — Liste éditée le <?= e(formaterDateHeure(date('Y-m-d H:i:s'))) ?> par <?= e($comptable['nom']) ?></div>

<table class="entete">
    <tr>
        <td style="width: 58px"><img class="logo" src="<?= $logo ?>" alt=""></td>
        <td>
            <div class="institut"><?= e(APP_NOM) ?></div>
            <div class="sous-titre"><?= e(APP_MENTION) ?> · <?= e(APP_VILLE) ?></div>
        </td>
        <td class="titre">
            <div class="grand">Liste des impayés</div>
            <div class="sous-titre"><?= $criteres ? 'Filtres : ' . e(implode(' · ', $criteres)) : 'Tous les frais non soldés' ?></div>
            <div class="sous-titre"><?= count($lignes) ?> frais · reste total <?= e(formaterMontant($totaux['reste'])) ?></div>
        </td>
    </tr>
</table>
<div class="filet"></div>

<table class="liste">
    <tr>
        <th>Élève</th><th>Matricule</th><th>Classe</th><th>Parent</th><th>Téléphone</th><th>Frais</th><th>Échéance</th>
        <th class="num">Montant</th><th class="num">Payé</th><th class="num">Reste</th><th>Statut</th>
    </tr>
    <?php foreach ($lignes as $f): ?>
        <tr>
            <td><?= e($f['eleve_nom'] . ' ' . $f['eleve_prenom']) ?></td>
            <td><?= e($f['matricule']) ?></td>
            <td><?= e($f['classe']) ?></td>
            <td><?= e($f['parent_nom']) ?></td>
            <td class="muted"><?= e($f['parent_telephone'] ?? '—') ?></td>
            <td><?= e($f['categorie']) ?></td>
            <td class="<?= $f['echeance'] < $aujourdhui ? 'retard' : '' ?>"><?= e(formaterDate($f['echeance'])) ?></td>
            <td class="num"><?= e(formaterMontant($f['montant'])) ?></td>
            <td class="num"><?= e(formaterMontant($f['montant_paye'])) ?></td>
            <td class="num"><strong><?= e(formaterMontant($f['reste'])) ?></strong></td>
            <td><?= e($statuts[$f['statut']]) ?></td>
        </tr>
    <?php endforeach; ?>
    <tr class="total">
        <td colspan="7">Total (<?= count($lignes) ?> frais)</td>
        <td class="num"><?= e(formaterMontant($totaux['du'])) ?></td>
        <td class="num"><?= e(formaterMontant($totaux['paye'])) ?></td>
        <td class="num"><?= e(formaterMontant($totaux['reste'])) ?></td>
        <td></td>
    </tr>
</table>
</body>
</html>
