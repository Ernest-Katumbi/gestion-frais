<?php
/**
 * Reçu de paiement (PDF, format A5 portrait, une page) — rendu par Dompdf.
 * Mise en page à base de tableaux (Dompdf ne gère pas flexbox/grid).
 * @var array  $p    paiement détaillé (Paiement::trouver)
 * @var string $qr   QR code (data URI PNG)
 * @var string $logo logo (data URI SVG)
 */
$eleve = $p['eleve_prenom'] . ' ' . $p['eleve_nom'];
$reste = (float) $p['frais_reste'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Reçu <?= e($p['recu_numero']) ?></title>
<style>
    @page { margin: 10mm 10mm 9mm; }
    * { font-family: "DejaVu Sans", sans-serif; }
    body { margin: 0; color: #0F172A; font-size: 8.2pt; line-height: 1.35; }
    table { border-collapse: collapse; width: 100%; }
    td { vertical-align: top; }
    .entete td { vertical-align: middle; }
    .logo { width: 54px; height: 54px; }
    .institut { font-size: 12pt; font-weight: bold; color: #062B6E; }
    .sous-titre { color: #64748B; font-size: 7.2pt; }
    .titre-recu { text-align: right; }
    .titre-recu .libelle { font-size: 6.8pt; letter-spacing: 1.2px; color: #64748B; text-transform: uppercase; }
    .titre-recu .numero { font-size: 10.5pt; font-weight: bold; }
    .titre-recu .date { font-size: 7pt; color: #64748B; }
    .filet { height: 2.5px; background: #0541B6; margin: 6px 0 9px; }
    .montant { background: #EAF0FC; border: 1px solid #CCDAF5; }
    .montant td { padding: 7px 10px; vertical-align: middle; }
    .montant .etiquette { color: #062B6E; font-size: 7.6pt; }
    .montant .valeur { font-size: 16pt; font-weight: bold; color: #062B6E; text-align: right; }
    .tampon { display: inline-block; margin-top: 2px; padding: 1px 6px; border: 1px solid #16A34A; color: #166534; font-size: 6.8pt; font-weight: bold; letter-spacing: .8px; }
    .colonnes td.col { width: 50%; }
    .colonnes td.col-g { padding-right: 8px; }
    .colonnes td.col-d { padding-left: 8px; }
    h2 { margin: 10px 0 3px; font-size: 7pt; letter-spacing: 1px; text-transform: uppercase; color: #0541B6; }
    .details td { padding: 2.5px 0; border-bottom: 0.5px solid #E2E8F0; }
    .details td.cle { width: 40%; color: #64748B; }
    .details td.val { font-weight: bold; }
    .num { text-align: right; white-space: nowrap; }
    .solde td { padding: 3.5px 8px; }
    .solde .ligne td { border-bottom: 0.5px solid #E2E8F0; }
    .solde .total td { background: #F8FAFC; font-weight: bold; }
    .reste-ok { color: #166534; }
    .reste-du { color: #92400E; }
    .pied { margin-top: 10px; border-top: 0.5px solid #E2E8F0; }
    .pied td { padding-top: 8px; vertical-align: middle; }
    .qr { width: 96px; height: 96px; }
    .ref { font-size: 7.2pt; white-space: nowrap; }
    .verif { font-size: 7pt; color: #334155; padding-left: 8px; }
    .verif strong { color: #0F172A; font-size: 7.6pt; }
    .mentions { margin-top: 8px; font-size: 6.2pt; color: #94A3B8; text-align: center; }
</style>
</head>
<body>

<table class="entete">
    <tr>
        <td style="width: 62px"><img class="logo" src="<?= $logo ?>" alt=""></td>
        <td>
            <div class="institut"><?= e(APP_NOM) ?></div>
            <div class="sous-titre"><?= e(APP_MENTION) ?> · <?= e(APP_VILLE) ?></div>
        </td>
        <td class="titre-recu">
            <div class="libelle">Reçu de paiement</div>
            <div class="numero"><?= e($p['recu_numero']) ?></div>
            <div class="date">Émis le <?= e(formaterDateHeure($p['date_emission'])) ?></div>
        </td>
    </tr>
</table>
<div class="filet"></div>

<table class="montant">
    <tr>
        <td>
            <div class="etiquette">Montant payé</div>
            <span class="tampon">PAIEMENT CONFIRMÉ</span>
        </td>
        <td class="valeur"><?= e(formaterMontant($p['montant'])) ?></td>
    </tr>
</table>

<table class="colonnes">
    <tr>
        <td class="col col-g">
            <h2>Élève</h2>
            <table class="details">
                <tr><td class="cle">Nom</td><td class="val"><?= e($eleve) ?></td></tr>
                <tr><td class="cle">Matricule</td><td class="val"><?= e($p['matricule']) ?></td></tr>
                <tr><td class="cle">Classe</td><td class="val"><?= e($p['classe']) ?></td></tr>
                <tr><td class="cle">Frais</td><td class="val"><?= e($p['categorie']) ?></td></tr>
                <tr><td class="cle">Échéance</td><td class="val"><?= e(formaterDate($p['echeance'])) ?></td></tr>
            </table>
        </td>
        <td class="col col-d">
            <h2>Paiement</h2>
            <table class="details">
                <tr><td class="cle">Date</td><td class="val"><?= e(formaterDateHeure($p['date_paiement'])) ?></td></tr>
                <tr><td class="cle">Mode</td><td class="val"><?= e(Paiement::MODES[$p['mode']]) ?></td></tr>
                <tr><td class="cle">Référence</td><td class="val ref"><?= e($p['reference']) ?></td></tr>
                <?php if (!empty($p['comptable_nom'])): ?>
                    <tr><td class="cle">Encaissé par</td><td class="val"><?= e($p['comptable_nom']) ?></td></tr>
                <?php else: ?>
                    <tr><td class="cle">Canal</td><td class="val">Paiement en ligne</td></tr>
                <?php endif; ?>
            </table>
        </td>
    </tr>
</table>

<h2>Situation du frais</h2>
<table class="solde">
    <tr class="ligne"><td>Montant du frais</td><td class="num"><?= e(formaterMontant($p['frais_montant'])) ?></td></tr>
    <tr class="ligne"><td>Total payé à ce jour</td><td class="num"><?= e(formaterMontant($p['frais_paye'])) ?></td></tr>
    <tr class="total">
        <td>Reste à payer</td>
        <td class="num <?= $reste > 0 ? 'reste-du' : 'reste-ok' ?>"><?= $reste > 0 ? e(formaterMontant($reste)) : 'Soldé' ?></td>
    </tr>
</table>

<table class="pied">
    <tr>
        <td style="width: 100px"><img class="qr" src="<?= $qr ?>" alt="QR code de vérification"></td>
        <td class="verif">
            <strong>Vérifier l'authenticité de ce reçu</strong><br>
            Scannez ce QR code : la page de vérification de l'<?= e(APP_NOM) ?> confirme le numéro,
            l'élève et le montant. Un reçu modifié ou falsifié est signalé comme invalide.
        </td>
    </tr>
</table>

<div class="mentions">
    Reçu généré électroniquement — valable sans signature ni cachet. À conserver jusqu'à la fin de l'année scolaire.
</div>

</body>
</html>
