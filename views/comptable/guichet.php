<?php
/**
 * Encaissement au guichet : 1. recherche de l'élève, 2. choix du frais et du montant.
 * @var string     $recherche
 * @var array      $resultats
 * @var array|null $eleve
 * @var array      $nonSoldes
 * @var array      $totaux
 * @var int        $nbSoldes
 * @var int        $idFraisChoisi
 * @var array|null $taux           taux de change en vigueur
 */
$ancien = Session::lireFlash('old', []);
$idChoisi = (int) ($ancien['id_frais'] ?? $idFraisChoisi);
if ($eleve && $nonSoldes !== [] && !in_array($idChoisi, array_map('intval', array_column($nonSoldes, 'id_frais')), true)) {
    $idChoisi = (int) $nonSoldes[0]['id_frais'];
}
$erreurMontant = erreur('montant');
?>
<div class="page-header">
    <div>
        <h2>Encaisser au guichet</h2>
        <p>Paiement en espèces : le reçu est généré immédiatement et le parent est notifié.</p>
    </div>
</div>

<section class="card mb-3">
    <form method="get" action="<?= url('/paiements/guichet') ?>" class="toolbar" role="search">
        <div class="input-icon search">
            <?= icone('search') ?>
            <input type="search" name="q" value="<?= e($eleve ? '' : $recherche) ?>" placeholder="Matricule, nom de l'élève ou du parent…" aria-label="Rechercher un élève" <?= $eleve ? '' : 'autofocus' ?>>
        </div>
        <button type="submit" class="btn btn-secondary" data-no-loading><?= icone('search') ?> Rechercher</button>
    </form>

    <?php if (!$eleve && $recherche !== ''): ?>
        <?php if ($resultats === []): ?>
            <?= View::partiel('vide', ['icone' => 'search', 'titre' => 'Aucun élève trouvé', 'texte' => 'Vérifiez le matricule ou l\'orthographe du nom.']) ?>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($resultats as $r): ?>
                    <li>
                        <span class="avatar avatar-sm"><?= e(initiales($r['prenom'] . ' ' . $r['nom'])) ?></span>
                        <a class="list__main" href="<?= url('/paiements/guichet', ['eleve' => (int) $r['id_eleve']]) ?>">
                            <strong><?= e($r['nom'] . ' ' . $r['prenom']) ?></strong>
                            <span><?= e($r['matricule']) ?> · <?= e($r['classe']) ?> · parent : <?= e($r['parent_nom']) ?></span>
                        </a>
                        <a class="btn btn-soft btn-sm" href="<?= url('/paiements/guichet', ['eleve' => (int) $r['id_eleve']]) ?>">Choisir <?= icone('arrow-right') ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php elseif (!$eleve): ?>
        <p class="card__body text-muted mb-0">Recherchez l'élève par son matricule (ex. IO-2026-001) ou son nom.</p>
    <?php endif; ?>
</section>

<?php if ($eleve): ?>
    <section class="card profile mb-3">
        <div class="profile__main">
            <span class="avatar avatar-lg"><?= e(initiales($eleve['prenom'] . ' ' . $eleve['nom'])) ?></span>
            <div>
                <h2 class="mb-0"><?= e($eleve['prenom'] . ' ' . $eleve['nom']) ?></h2>
                <div class="profile__meta">
                    <span class="num"><?= icone('tag', 'icon-sm') ?> <?= e($eleve['matricule']) ?></span>
                    <span><?= icone('school', 'icon-sm') ?> <?= e($eleve['classe']) ?></span>
                    <span><?= icone('user', 'icon-sm') ?> <?= e($eleve['parent_nom']) ?></span>
                </div>
            </div>
        </div>
        <div class="profile__totaux">
            <div><span>Total dû</span><strong class="num"><?= e(formaterMontant($totaux['du'])) ?></strong></div>
            <div><span>Payé</span><strong class="num text-success"><?= e(formaterMontant($totaux['paye'])) ?></strong></div>
            <div><span>Reste</span><strong class="num<?= $totaux['reste'] > 0 ? ' text-danger' : '' ?>"><?= e(formaterMontant($totaux['reste'])) ?></strong></div>
        </div>
    </section>

    <?php if ($nonSoldes === []): ?>
        <section class="card">
            <?= View::partiel('vide', [
                'icone' => 'check-circle',
                'titre' => $nbSoldes > 0 ? 'Tous les frais de cet élève sont soldés' : 'Aucun frais affecté à cet élève',
                'texte' => $nbSoldes > 0 ? 'Il n\'y a rien à encaisser pour le moment.' : 'Affectez d\'abord des frais à sa classe.',
                'action' => '<a href="' . url('/paiements/guichet') . '" class="btn btn-secondary">' . icone('arrow-left') . ' Autre élève</a>',
            ]) ?>
        </section>
    <?php else: ?>
        <form method="post" action="<?= url('/paiements/guichet') ?>" class="card" data-validate data-guichet<?= attributsDevises($taux) ?>
              data-confirm="Confirmez-vous l'encaissement de ce montant en espèces ? Le reçu sera émis immédiatement."
              data-confirm-titre="Enregistrer le paiement ?" data-confirm-bouton="Encaisser" data-confirm-type="primary">
            <?= csrf_champ() ?>
            <input type="hidden" name="id_eleve" value="<?= (int) $eleve['id_eleve'] ?>">
            <div class="card__header"><h3>1. Frais à régler</h3><span class="text-small text-muted"><?= count($nonSoldes) ?> frais non soldé<?= count($nonSoldes) > 1 ? 's' : '' ?></span></div>
            <div class="card__body radio-cards">
                <?php foreach ($nonSoldes as $f): ?>
                    <?php $id = (int) $f['id_frais']; $echu = $f['echeance'] < date('Y-m-d'); ?>
                    <label class="radio-card">
                        <input type="radio" name="id_frais" value="<?= $id ?>" required
                               data-reste="<?= e(number_format((float) $f['reste'], 2, '.', '')) ?>"
                               data-minimum="<?= e(number_format(Frais::versementMinimal((float) $f['reste']), 2, '.', '')) ?>"
                               <?= $id === $idChoisi ? 'checked' : '' ?>>
                        <span class="radio-card__body">
                            <span class="radio-card__head">
                                <strong><?= e($f['categorie']) ?></strong>
                                <?= badgeStatut($f['statut']) ?>
                            </span>
                            <span class="radio-card__meta <?= $echu ? 'text-danger' : '' ?>">Échéance <?= e(formaterDate($f['echeance'])) ?> · <?= e(libelleEcheance($f['echeance'])) ?></span>
                            <span class="radio-card__amounts">
                                <span>Montant <b class="num"><?= e(formaterMontant($f['montant'])) ?></b></span>
                                <span>Payé <b class="num"><?= e(formaterMontant($f['montant_paye'])) ?></b></span>
                                <span>Reste <b class="num"><?= e(formaterMontant($f['reste'])) ?></b></span>
                            </span>
                            <span class="progress"><span class="progress__bar" style="width: <?= pourcentagePaye($f['montant_paye'], $f['montant']) ?>%"></span></span>
                        </span>
                    </label>
                <?php endforeach; ?>
                <?php if ($nbSoldes > 0): ?>
                    <p class="text-small text-muted mb-0"><?= $nbSoldes ?> autre<?= $nbSoldes > 1 ? 's' : '' ?> frais déjà soldé<?= $nbSoldes > 1 ? 's' : '' ?>.</p>
                <?php endif; ?>
            </div>

            <div class="card__header card__header--top"><h3>2. Montant versé</h3></div>
            <div class="card__body">
                <div class="form-grid">
                    <?= View::partiel('montant_devise', [
                        'libelle' => 'Montant reçu', 'taux' => $taux, 'ancien' => $ancien,
                        'erreurMontant' => $erreurMontant, 'valeurDefaut' => '',
                    ]) ?>
                    <div class="field">
                        <span class="label">Mode de paiement</span>
                        <div class="static-field"><?= icone('banknote') ?> Espèces (guichet)</div>
                    </div>
                </div>
                <div class="apercu" data-apercu hidden></div>
            </div>
            <div class="card__footer form-actions mt-0">
                <a href="<?= url('/paiements/guichet') ?>" class="btn btn-secondary">Autre élève</a>
                <button type="submit" class="btn btn-primary btn-lg"><?= icone('check') ?> Enregistrer le paiement</button>
            </div>
        </form>
    <?php endif; ?>
<?php endif; ?>
