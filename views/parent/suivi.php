<?php
/**
 * Suivi d'un paiement en ligne. En attente : la page interroge le statut toutes les
 * 3 secondes et se recharge dès que la passerelle a confirmé ou refusé le paiement.
 * @var array       $p
 * @var string|null $raison      motif d'échec communiqué par la passerelle
 * @var string      $urlPaiement page de la passerelle (téléphone simulé ou page de carte)
 */
$idFrais = (int) $p['id_frais'];
$mobile = $p['mode'] === 'mobile_money';
?>
<section class="card suivi"<?= $p['statut'] === 'en_attente' ? ' data-suivi-url="' . e(url('/parent/paiements/' . (int) $p['id_paiement'] . '/statut')) . '"' : '' ?>>
    <?php if ($p['statut'] === 'en_attente'): ?>
        <div class="suivi__etat is-attente">
            <span class="suivi__spinner" aria-hidden="true"></span>
            <h2>En attente de confirmation…</h2>
            <p>
                <?= $mobile
                    ? 'Une demande de paiement a été envoyée sur votre téléphone. Saisissez votre code PIN pour confirmer.'
                    : 'Terminez le paiement sur la page sécurisée de la passerelle.' ?>
            </p>
            <a href="<?= e($urlPaiement) ?>" class="btn btn-primary"<?= $mobile ? ' target="_blank" rel="noopener"' : '' ?>>
                <?= icone($mobile ? 'smartphone' : 'credit-card') ?>
                <?= $mobile ? 'Ouvrir mon téléphone (simulateur)' : 'Ouvrir la page de paiement' ?>
            </a>
            <p class="text-small text-muted mt-2 mb-0" data-suivi-info aria-live="polite">Cette page se met à jour automatiquement.</p>
        </div>
    <?php elseif ($p['statut'] === 'reussi'): ?>
        <div class="suivi__etat is-reussi">
            <span class="suivi__icone"><?= icone('check-circle') ?></span>
            <h2>Paiement confirmé</h2>
            <p>Merci ! Votre paiement de <strong class="num"><?= e(formaterMontant($p['montant'])) ?></strong> a bien été reçu.
                <?= (float) $p['frais_reste'] > 0
                    ? 'Il reste ' . e(formaterMontant($p['frais_reste'])) . ' à payer sur ce frais.'
                    : 'Ce frais est désormais entièrement réglé.' ?></p>
            <div class="suivi__actions">
                <?php if ($p['id_recu']): ?>
                    <a href="<?= url('/recus/' . (int) $p['id_recu'], ['telecharger' => 1]) ?>" class="btn btn-primary"><?= icone('download') ?> Télécharger le reçu</a>
                <?php endif; ?>
                <a href="<?= url('/parent') ?>" class="btn btn-secondary"><?= icone('home') ?> Retour à l'accueil</a>
            </div>
        </div>
    <?php else: ?>
        <div class="suivi__etat is-echoue">
            <span class="suivi__icone"><?= icone('alert-circle') ?></span>
            <h2>Le paiement n'a pas abouti</h2>
            <p><?= $raison ? 'Motif : <strong>' . e($raison) . '</strong>. ' : '' ?>Aucun montant n'a été débité.</p>
            <div class="suivi__actions">
                <a href="<?= url('/parent/frais/' . $idFrais . '/payer') ?>" class="btn btn-primary"><?= icone('refresh') ?> Réessayer</a>
                <a href="<?= url('/parent/frais/' . $idFrais) ?>" class="btn btn-secondary">Retour au frais</a>
            </div>
        </div>
    <?php endif; ?>

    <dl class="dl suivi__details">
        <dt>Élève</dt><dd><?= e($p['eleve_prenom'] . ' ' . $p['eleve_nom']) ?></dd>
        <dt>Frais</dt><dd><?= e($p['categorie']) ?></dd>
        <dt>Montant</dt><dd class="num fw-600"><?= e(formaterMontant($p['montant'])) ?></dd>
        <dt>Moyen</dt><dd><?= e(Paiement::MODES[$p['mode']]) ?></dd>
        <dt>Référence</dt><dd class="num"><?= e($p['reference']) ?></dd>
        <dt>Statut</dt><dd><?= badgeStatut($p['statut']) ?></dd>
    </dl>
</section>
