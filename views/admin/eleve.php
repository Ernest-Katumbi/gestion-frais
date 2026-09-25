<?php
/**
 * Fiche d'un élève : identité, parent, fratrie (les frais sont ajoutés à l'itération 3).
 * @var array      $eleve
 * @var array      $freres       autres enfants du même parent
 * @var bool       $supprimable  false si des paiements existent
 * @var array|null $provisoire
 */
$id = (int) $eleve['id_eleve'];
$nom = $eleve['prenom'] . ' ' . $eleve['nom'];
?>
<?php if ($provisoire): ?>
    <?= View::partiel('mot_de_passe_provisoire', ['provisoire' => $provisoire]) ?>
<?php endif; ?>

<section class="card profile mb-3">
    <div class="profile__main">
        <span class="avatar avatar-lg"><?= e(initiales($nom)) ?></span>
        <div>
            <h2 class="mb-0"><?= e($nom) ?></h2>
            <div class="profile__meta">
                <span class="num"><?= icone('tag', 'icon-sm') ?> <?= e($eleve['matricule']) ?></span>
                <span><?= icone('school', 'icon-sm') ?> <?= e($eleve['classe']) ?></span>
                <span><?= icone('calendar', 'icon-sm') ?> Inscrit(e) le <?= e(formaterDate($eleve['date_inscription'])) ?></span>
            </div>
        </div>
    </div>
    <div class="profile__actions">
        <a href="<?= url("/eleves/$id/modifier") ?>" class="btn btn-secondary"><?= icone('pencil') ?> Modifier</a>
        <?php if ($supprimable): ?>
            <form method="post" action="<?= url("/eleves/$id/supprimer") ?>"
                  data-confirm="La fiche de <?= e($nom) ?> et ses frais non payés seront supprimés définitivement."
                  data-confirm-titre="Supprimer cet élève ?" data-confirm-bouton="Supprimer">
                <?= csrf_champ() ?>
                <button type="submit" class="btn btn-danger" data-no-loading><?= icone('trash') ?> Supprimer</button>
            </form>
        <?php else: ?>
            <button type="button" class="btn btn-danger" disabled title="Des paiements existent pour cet élève"><?= icone('trash') ?> Supprimer</button>
        <?php endif; ?>
    </div>
</section>

<?php if (!$supprimable): ?>
    <div class="alert alert-info mb-3">
        <?= icone('info') ?>
        <div>Des paiements ont été enregistrés pour cet élève : sa fiche ne peut pas être supprimée afin de conserver l'historique financier.</div>
    </div>
<?php endif; ?>

<div class="grid grid-main-side">
    <section class="card">
        <div class="card__header"><h3>Frais scolaires</h3></div>
        <?= View::partiel('vide', [
            'icone' => 'receipt',
            'titre' => 'Aucun frais affecté pour l\'instant',
            'texte' => 'Les frais (minerval, inscription, examens) apparaîtront ici dès que le comptable les aura affectés à la classe.',
        ]) ?>
    </section>

    <aside class="grid" style="align-content:start">
        <section class="card">
            <div class="card__header">
                <h3>Parent ou tuteur</h3>
                <a href="<?= url('/utilisateurs/' . (int) $eleve['id_parent'] . '/modifier') ?>" class="btn btn-ghost btn-sm"><?= icone('pencil') ?> Compte</a>
            </div>
            <div class="card__body">
                <div class="flex mb-2">
                    <span class="avatar"><?= e(initiales($eleve['parent_nom'])) ?></span>
                    <div style="min-width:0">
                        <strong><?= e($eleve['parent_nom']) ?></strong>
                        <?php if ($eleve['parent_statut'] !== 'actif'): ?><span class="badge badge-inactif">Désactivé</span><?php endif; ?>
                    </div>
                </div>
                <dl class="dl">
                    <dt>E-mail</dt><dd class="text-break"><?= e($eleve['parent_email']) ?></dd>
                    <dt>Téléphone</dt><dd class="num"><?= e($eleve['parent_telephone'] ?: '—') ?></dd>
                    <dt>Adresse</dt><dd><?= e($eleve['parent_adresse'] ?: '—') ?></dd>
                </dl>
            </div>
        </section>

        <section class="card">
            <div class="card__header"><h3>Frères et sœurs</h3></div>
            <?php if ($freres === []): ?>
                <p class="card__body text-muted text-small mb-0">Aucun autre enfant rattaché à ce parent.</p>
            <?php else: ?>
                <ul class="list">
                    <?php foreach ($freres as $f): ?>
                        <li>
                            <span class="avatar avatar-sm"><?= e(initiales($f['prenom'] . ' ' . $f['nom'])) ?></span>
                            <a class="list__main" href="<?= url('/eleves/' . (int) $f['id_eleve']) ?>">
                                <strong><?= e($f['prenom'] . ' ' . $f['nom']) ?></strong>
                                <span><?= e($f['classe']) ?> · <?= e($f['matricule']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </aside>
</div>
