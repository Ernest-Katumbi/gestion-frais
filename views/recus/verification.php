<?php
/**
 * Résultat de la vérification publique d'un reçu.
 * Seules les informations essentielles sont affichées (pas de coordonnées du parent).
 * @var array|null $paiement
 * @var string     $numero
 */
?>
<div class="auth-card verif-card">
    <div class="auth-brand">
        <img src="<?= asset('img/logo-institut.png') ?>" alt="Logo de l'<?= e(APP_NOM) ?>" width="96" height="96">
        <h1><?= e(APP_NOM) ?></h1>
        <p>Vérification des reçus de paiement</p>
    </div>

    <?php if ($paiement): ?>
        <div class="card">
            <div class="verif-result is-valid">
                <span class="verif-result__icon"><?= icone('shield-check') ?></span>
                <div>
                    <h2>Reçu authentique</h2>
                    <p>Ce reçu a bien été émis par l'<?= e(APP_NOM) ?>.</p>
                </div>
            </div>
            <dl class="dl verif-details">
                <dt>Numéro</dt><dd class="num"><?= e($paiement['recu_numero']) ?></dd>
                <dt>Émis le</dt><dd><?= e(formaterDateHeure($paiement['date_emission'])) ?></dd>
                <dt>Élève</dt><dd><?= e($paiement['eleve_prenom'] . ' ' . $paiement['eleve_nom']) ?></dd>
                <dt>Matricule</dt><dd class="num"><?= e($paiement['matricule']) ?></dd>
                <dt>Classe</dt><dd><?= e($paiement['classe']) ?></dd>
                <dt>Frais</dt><dd><?= e($paiement['categorie']) ?></dd>
                <dt>Montant payé</dt><dd class="num fw-600"><?= e(formaterMontant($paiement['montant'])) ?></dd>
                <dt>Payé le</dt><dd><?= e(formaterDateHeure($paiement['date_paiement'])) ?></dd>
                <dt>Mode</dt><dd><?= e(Paiement::MODES[$paiement['mode']]) ?></dd>
            </dl>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="verif-result is-invalid">
                <span class="verif-result__icon"><?= icone('shield-x') ?></span>
                <div>
                    <h2>Reçu invalide</h2>
                    <p>Ce reçu n'a pas pu être authentifié<?= $numero !== '' ? ' (n° ' . e(mb_substr($numero, 0, 40)) . ')' : '' ?>.
                       Il a peut-être été modifié ou falsifié. Contactez la comptabilité de l'Institut.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <p class="auth-footer"><?= e(APP_NOM) ?> · <?= e(APP_VILLE) ?></p>
</div>
