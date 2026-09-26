<?php
/**
 * Graphique en barres verticales (HTML/CSS, sans bibliothèque) : une seule série,
 * donc une seule teinte et pas de légende. Info-bulle au survol ou au clavier,
 * tableau des données accessible sous le graphique.
 * @var array  $points  [['court' => '12/09', 'long' => 'ven. 12/09/2026', 'valeur' => float, 'nb' => int], …]
 * @var string $serie   nom de la mesure (ex. « Encaissements »)
 * @var bool   $tableau afficher le lien « Voir les données » (défaut : oui)
 */
$max = max([0.0, ...array_column($points, 'valeur')]);
// Maximum « rond » de l'axe : 1, 2 ou 5 × 10^n au-dessus de la plus grande valeur.
$echelle = 1.0;
if ($max > 0) {
    $puissance = 10 ** floor(log10($max));
    foreach ([1, 2, 5, 10] as $facteur) {
        if ($facteur * $puissance >= $max) {
            $echelle = $facteur * $puissance;
            break;
        }
    }
}
$n = count($points);
$pasEtiquette = $n <= 16 ? 1 : (int) ceil($n / 10);
$total = array_sum(array_column($points, 'valeur'));
$tableau ??= true;
?>
<figure class="chart" aria-label="<?= e($serie) ?> : graphique en barres">
    <div class="chart__plot">
        <div class="chart__grid" aria-hidden="true">
            <?php foreach ([1, 0.5, 0] as $part): ?>
                <div class="chart__gridline" style="bottom: <?= $part * 100 ?>%"><span><?= e(number_format($echelle * $part, 0, ',', "\u{00A0}")) ?></span></div>
            <?php endforeach; ?>
        </div>
        <div class="chart__bars" style="--n: <?= max(1, $n) ?>">
            <?php foreach ($points as $i => $p): ?>
                <?php $hauteur = $echelle > 0 ? round($p['valeur'] / $echelle * 100, 2) : 0; ?>
                <div class="chart__col<?= $i >= $n - 3 && $n > 6 ? ' is-fin' : '' ?><?= $i < 2 && $n > 6 ? ' is-debut' : '' ?>" tabindex="0"
                     data-tip="<?= e($p['long'] . ' : ' . formaterMontant($p['valeur']) . ($p['nb'] ? ' · ' . $p['nb'] . ' paiement' . ($p['nb'] > 1 ? 's' : '') : '')) ?>"
                     aria-label="<?= e($p['long'] . ' : ' . formaterMontant($p['valeur'])) ?>">
                    <span class="chart__bar<?= $p['valeur'] <= 0 ? ' is-zero' : '' ?>" style="height: <?= $hauteur ?>%"></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="chart__axis" style="--n: <?= max(1, $n) ?>" aria-hidden="true">
        <?php foreach ($points as $i => $p): ?>
            <span><?= $i % $pasEtiquette === 0 ? e($p['court']) : '' ?></span>
        <?php endforeach; ?>
    </div>
    <figcaption class="chart__legende">
        <?= e($serie) ?> en <?= e(DEVISE) ?> · total <strong class="num"><?= e(formaterMontant($total)) ?></strong>
    </figcaption>
    <?php if ($tableau): ?>
        <details class="chart__donnees">
            <summary>Voir les données</summary>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Période</th><th class="col-num">Paiements</th><th class="col-num">Montant</th></tr></thead>
                    <tbody>
                        <?php foreach ($points as $p): ?>
                            <tr><td><?= e($p['long']) ?></td><td class="col-num num"><?= (int) $p['nb'] ?></td><td class="col-num num"><?= e(formaterMontant($p['valeur'])) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
    <?php endif; ?>
</figure>
