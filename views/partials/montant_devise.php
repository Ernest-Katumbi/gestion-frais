<?php
/**
 * Saisie d'un versement : choix de la devise (francs ou dollars si un taux existe),
 * montant, aide, erreur et aperçu de la conversion (app.js, formulaire [data-guichet]).
 * @var string      $libelle       ex. « Montant reçu »
 * @var array|null  $taux          taux en vigueur (TauxChange::actuel)
 * @var array       $ancien        saisie précédente (erreur de validation)
 * @var string|null $erreurMontant
 * @var string      $valeurDefaut  montant proposé dans la devise de base
 */
$devisesAcceptees = Monnaie::devisesAcceptees($taux);
$deviseChoisie = in_array($ancien['devise'] ?? '', $devisesAcceptees, true) ? $ancien['devise'] : DEVISE;
?>
<div class="field<?= $erreurMontant ? ' has-error' : '' ?>">
    <label for="montant"><?= e($libelle) ?><span class="required" aria-hidden="true">*</span></label>
    <?php if (count($devisesAcceptees) > 1): ?>
        <div class="segmented devises" role="radiogroup" aria-label="Devise du versement">
            <?php foreach ($devisesAcceptees as $code): ?>
                <label><input type="radio" name="devise" value="<?= e($code) ?>"<?= $code === $deviseChoisie ? ' checked' : '' ?>><span><?= e(infosDevise($code)['nom']) ?> (<?= e(symboleDevise($code)) ?>)</span></label>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <input type="hidden" name="devise" value="<?= e(DEVISE) ?>">
    <?php endif; ?>
    <div class="input-suffix">
        <input type="number" id="montant" name="montant" required inputmode="decimal"
               step="<?= e(decimalesDevise($deviseChoisie) > 0 ? '0.01' : '1') ?>" min="0"
               value="<?= e($ancien['montant'] ?? $valeurDefaut) ?>" class="input-montant" aria-describedby="montant-aide">
        <span data-suffixe-devise><?= e(symboleDevise($deviseChoisie)) ?></span>
    </div>
    <span class="hint" id="montant-aide" data-montant-aide>Versement partiel accepté, jamais supérieur au reste.</span>
    <?php if ($taux): ?>
        <span class="hint">Taux du jour : <?= e(formaterTaux($taux['taux'])) ?></span>
    <?php endif; ?>
    <?php if ($erreurMontant): ?><span class="error"><?= icone('alert-circle') ?><?= e($erreurMontant) ?></span><?php endif; ?>
</div>
