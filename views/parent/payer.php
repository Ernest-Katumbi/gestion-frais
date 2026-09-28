<?php
/**
 * Paiement en ligne d'un frais : montant (reste proposé) et moyen de paiement.
 * @var array $f          frais détaillé
 * @var array $parent
 * @var array $operateurs code => libellé
 * @var array|null $taux    taux de change en vigueur
 */
$ancien = Session::lireFlash('old', []);
$mode = $ancien['mode'] ?? 'mobile_money';
$reste = (float) $f['reste'];
$erreurMontant = erreur('montant');
?>
<a href="<?= url('/parent/frais/' . (int) $f['id_frais']) ?>" class="btn btn-ghost btn-sm mb-2"><?= icone('arrow-left') ?> Retour au frais</a>

<form method="post" action="<?= url('/parent/frais/' . (int) $f['id_frais'] . '/payer') ?>" class="card form paiement-form" data-validate data-guichet<?= attributsDevises($taux) ?>>
    <?= csrf_champ() ?>
    <input type="hidden" data-frais-unique
           data-reste="<?= e(number_format($reste, 2, '.', '')) ?>"
           data-minimum="<?= e(number_format(Frais::versementMinimal($reste), 2, '.', '')) ?>">

    <div class="card__body paiement-form__resume">
        <p class="text-small text-muted mb-0"><?= e($f['eleve_prenom'] . ' ' . $f['eleve_nom']) ?> · <?= e($f['classe']) ?></p>
        <h2 class="mb-1"><?= e($f['categorie']) ?></h2>
        <div class="frais-card__montants">
            <div><span>Montant</span><strong class="num"><?= e(formaterMontant($f['montant'])) ?></strong></div>
            <div><span>Déjà payé</span><strong class="num"><?= e(formaterMontant($f['montant_paye'])) ?></strong></div>
            <div><span>Reste</span><strong class="num text-danger"><?= e(formaterMontant($reste)) ?></strong></div>
        </div>
    </div>

    <div class="card__header card__header--top"><h3>1. Montant à payer</h3></div>
    <div class="card__body">
        <?= View::partiel('montant_devise', [
            'libelle' => 'Montant à payer', 'taux' => $taux, 'ancien' => $ancien,
            'erreurMontant' => $erreurMontant, 'valeurDefaut' => number_format($reste, decimalesDevise(), '.', ''),
        ]) ?>
        <div class="apercu" data-apercu hidden></div>
    </div>

    <div class="card__header card__header--top"><h3>2. Moyen de paiement</h3></div>
    <div class="card__body">
        <div class="modes">
            <label class="mode-card">
                <input type="radio" name="mode" value="mobile_money" required<?= $mode === 'mobile_money' ? ' checked' : '' ?>>
                <span class="mode-card__icon"><?= icone('smartphone') ?></span>
                <span><strong>Mobile Money</strong><span class="text-small text-muted d-block">M-Pesa, Orange Money, Airtel Money</span></span>
            </label>
            <label class="mode-card">
                <input type="radio" name="mode" value="carte"<?= $mode === 'carte' ? ' checked' : '' ?>>
                <span class="mode-card__icon"><?= icone('credit-card') ?></span>
                <span><strong>Carte bancaire</strong><span class="text-small text-muted d-block">Visa, Mastercard</span></span>
            </label>
        </div>

        <div class="form-grid mt-2" data-si-mode="mobile_money">
            <?= View::partiel('champ', [
                'nom' => 'operateur', 'libelle' => 'Opérateur', 'type' => 'select', 'requis' => true,
                'options' => ['' => 'Choisir…'] + $operateurs, 'valeur' => 'mpesa',
            ]) ?>
            <?= View::partiel('champ', [
                'nom' => 'telephone', 'libelle' => 'Numéro Mobile Money', 'type' => 'tel', 'requis' => true, 'icone' => 'phone',
                'valeur' => (string) $parent['telephone'],
                'attributs' => 'maxlength="20" pattern="\+?[0-9 ]{9,20}" data-pattern-message="Numéro invalide (ex. +243 97 123 4567)." autocomplete="tel"',
            ]) ?>
            <p class="span-2 text-small text-muted mb-0"><?= icone('info', 'icon-sm') ?> Une demande de confirmation sera envoyée sur ce téléphone : validez-la avec votre code PIN.</p>
        </div>
        <div class="alert alert-info mt-2" data-si-mode="carte" hidden>
            <?= icone('lock') ?>
            <div>Vous allez être redirigé vers la <strong>page sécurisée de la passerelle de paiement</strong> pour saisir votre carte. Vos données de carte ne sont jamais transmises ni conservées par l'<?= e(APP_NOM) ?>.</div>
        </div>
    </div>

    <div class="card__footer form-actions mt-0">
        <a href="<?= url('/parent/frais/' . (int) $f['id_frais']) ?>" class="btn btn-secondary">Annuler</a>
        <button type="submit" class="btn btn-primary btn-lg"><?= icone('lock') ?> Payer maintenant</button>
    </div>
</form>
