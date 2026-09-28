<?php
/**
 * Taux de change : taux en vigueur, saisie du taux du jour (comptable) et historique.
 * @var array|null $actuel
 * @var array      $historique
 * @var bool       $peutModifier
 * @var array|null $confirmer   nouveau taux à confirmer (forte variation)
 */
$base = infosDevise();
$etrangere = infosDevise(DEVISE_ETRANGERE);
?>
<div class="page-header">
    <div>
        <h2>Taux de change</h2>
        <p>Les frais, soldes et rapports sont tenus en <?= e($base['nom']) ?> (<?= e($base['symbole']) ?>). Les paiements en <?= e($etrangere['nom']) ?> (<?= e($etrangere['symbole']) ?>) sont convertis au taux en vigueur au moment du paiement.</p>
    </div>
</div>

<div class="grid grid-side-main">
    <div class="grid" style="align-content:start">
        <section class="card taux-actuel">
            <div class="card__body">
                <p class="stat__label">Taux en vigueur</p>
                <?php if ($actuel): ?>
                    <p class="taux-actuel__valeur num"><?= e(formaterTaux($actuel['taux'])) ?></p>
                    <p class="text-small text-muted mb-0">Depuis le <?= e(formaterDateHeure($actuel['date_application'])) ?> · saisi par <?= e($actuel['saisi_par']) ?></p>
                <?php else: ?>
                    <p class="taux-actuel__valeur text-muted">Aucun taux</p>
                    <p class="text-small text-muted mb-0">Tant qu'aucun taux n'est saisi, seuls les paiements en <?= e($base['symbole']) ?> sont acceptés.</p>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($peutModifier): ?>
            <section class="card">
                <div class="card__header"><h3>Taux du jour</h3></div>
                <form method="post" action="<?= url('/taux-change') ?>" class="card__body" data-validate
                      data-confirm="Ce taux s'appliquera immédiatement à tous les nouveaux paiements en <?= e($etrangere['symbole']) ?>."
                      data-confirm-titre="Enregistrer le nouveau taux ?" data-confirm-bouton="Enregistrer" data-confirm-type="primary">
                    <?= csrf_champ() ?>
                    <?php if ($confirmer): ?>
                        <input type="hidden" name="confirme" value="1">
                        <div class="alert alert-warning mb-2">
                            <?= icone('alert-triangle') ?>
                            <div>Variation de <?= e(number_format($confirmer['variation'] * 100, 0, ',', ' ')) ?> % par rapport au taux en vigueur. Vérifiez la saisie puis validez à nouveau pour confirmer.</div>
                        </div>
                    <?php endif; ?>
                    <div class="field<?= erreur('taux') ? ' has-error' : '' ?>">
                        <label for="taux">Nombre de <?= e($base['symbole']) ?> pour 1 <?= e($etrangere['symbole']) ?><span class="required" aria-hidden="true">*</span></label>
                        <div class="input-suffix">
                            <input type="number" id="taux" name="taux" required min="0.000001" step="any" inputmode="decimal"
                                   value="<?= e(old('taux', $actuel ? rtrim(rtrim($actuel['taux'], '0'), '.') : '')) ?>" placeholder="2850">
                            <span><?= e($base['symbole']) ?></span>
                        </div>
                        <span class="hint">Exemple : 2850 signifie 1 <?= e($etrangere['symbole']) ?> = 2 850 <?= e($base['symbole']) ?>.</span>
                        <?php if (erreur('taux')): ?><span class="error"><?= icone('alert-circle') ?><?= e(erreur('taux')) ?></span><?php endif; ?>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-block"><?= icone('check') ?> Appliquer ce taux</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </div>

    <section class="card" style="align-self:start">
        <div class="card__header"><h3>Historique des taux</h3><span class="text-small text-muted">30 derniers</span></div>
        <?php if ($historique === []): ?>
            <?= View::partiel('vide', ['icone' => 'refresh', 'titre' => 'Aucun taux saisi', 'texte' => 'Le premier taux saisi apparaîtra ici.']) ?>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table-stack">
                    <thead><tr><th>Date d'application</th><th class="col-num">Taux</th><th>Saisi par</th></tr></thead>
                    <tbody>
                        <?php foreach ($historique as $i => $t): ?>
                            <tr>
                                <td class="cell-main">
                                    <strong class="num"><?= e(formaterDateHeure($t['date_application'])) ?></strong>
                                    <?php if ($i === 0): ?><span class="badge badge-actif">En vigueur</span><?php endif; ?>
                                </td>
                                <td data-label="Taux" class="col-num num fw-600"><?= e(formaterTaux($t['taux'])) ?></td>
                                <td data-label="Saisi par"><?= e($t['saisi_par']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
