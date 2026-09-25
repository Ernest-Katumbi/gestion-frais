<?php
/**
 * Liste des utilisateurs (administrateur).
 * @var array      $utilisateurs
 * @var array      $filtres
 * @var array      $pagination
 * @var array|null $provisoire  mot de passe provisoire à afficher une seule fois
 */
$moi = utilisateur();
$filtreActif = $filtres['q'] !== '' || $filtres['role'] !== '' || $filtres['statut'] !== '';
?>
<div class="page-header">
    <div>
        <h2>Utilisateurs</h2>
        <p>Comptes des administrateurs, comptables et parents d'élèves.</p>
    </div>
    <div class="page-header__actions">
        <a href="<?= url('/utilisateurs/nouveau') ?>" class="btn btn-primary"><?= icone('user-plus') ?> Nouvel utilisateur</a>
    </div>
</div>

<?php if ($provisoire): ?>
    <div class="alert alert-olive mb-3" role="status">
        <?= icone('key') ?>
        <div style="flex:1">
            <strong>Mot de passe provisoire de <?= e($provisoire['nom']) ?></strong>
            <p class="mb-0">Communiquez-le à l'utilisateur (<?= e($provisoire['email']) ?>). Il ne sera plus affiché ; une copie de l'e-mail de bienvenue a été enregistrée dans le journal des e-mails.</p>
            <div class="secret-box">
                <code><?= e($provisoire['mot_de_passe']) ?></code>
                <button type="button" class="btn btn-secondary btn-sm" data-copy="<?= e($provisoire['mot_de_passe']) ?>"><?= icone('copy') ?> Copier</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<section class="card">
    <form method="get" action="<?= url('/utilisateurs') ?>" class="toolbar" role="search">
        <div class="input-icon search">
            <?= icone('search') ?>
            <input type="search" name="q" value="<?= e($filtres['q']) ?>" placeholder="Rechercher un nom, un e-mail, un téléphone…" aria-label="Rechercher">
        </div>
        <select name="role" data-autosubmit aria-label="Filtrer par rôle">
            <option value="">Tous les rôles</option>
            <?php foreach (User::ROLES as $cle => $libelle): ?>
                <option value="<?= e($cle) ?>"<?= $filtres['role'] === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="statut" data-autosubmit aria-label="Filtrer par statut">
            <option value="">Tous les statuts</option>
            <option value="actif"<?= $filtres['statut'] === 'actif' ? ' selected' : '' ?>>Actifs</option>
            <option value="inactif"<?= $filtres['statut'] === 'inactif' ? ' selected' : '' ?>>Désactivés</option>
        </select>
        <button type="submit" class="btn btn-secondary" data-no-loading><?= icone('filter') ?> Filtrer</button>
        <?php if ($filtreActif): ?>
            <a href="<?= url('/utilisateurs') ?>" class="btn btn-ghost">Réinitialiser</a>
        <?php endif; ?>
    </form>

    <?php if ($utilisateurs === []): ?>
        <?= View::partiel('vide', [
            'icone' => 'users',
            'titre' => $filtreActif ? 'Aucun utilisateur ne correspond' : 'Aucun utilisateur pour l\'instant',
            'texte' => $filtreActif ? 'Essayez une autre recherche ou réinitialisez les filtres.' : 'Créez le premier compte pour commencer.',
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table-stack">
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Rôle</th>
                        <th>Téléphone</th>
                        <th>Créé le</th>
                        <th>Statut</th>
                        <th class="col-actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($utilisateurs as $u): ?>
                        <?php $estMoi = (int) $u['id_utilisateur'] === (int) $moi['id_utilisateur']; ?>
                        <tr class="<?= $u['statut'] === 'inactif' ? 'is-inactive' : '' ?>">
                            <td class="cell-main">
                                <div class="cell-user">
                                    <span class="avatar avatar-sm"><?= e(initiales($u['nom'])) ?></span>
                                    <div>
                                        <strong><?= e($u['nom']) ?><?= $estMoi ? ' <span class="text-muted text-small">(vous)</span>' : '' ?></strong>
                                        <span><?= e($u['email']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Rôle">
                                <span class="badge badge-<?= e($u['role']) ?> no-dot"><?= e(libelleRole($u['role'])) ?></span>
                                <?php if ($u['role'] === 'parent' && (int) $u['nb_enfants'] > 0): ?>
                                    <span class="text-small text-muted"><?= (int) $u['nb_enfants'] ?> enfant<?= $u['nb_enfants'] > 1 ? 's' : '' ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Téléphone" class="num"><?= e($u['telephone'] ?: '—') ?></td>
                            <td data-label="Créé le" class="num"><?= e(formaterDate($u['date_creation'])) ?></td>
                            <td data-label="Statut"><span class="badge badge-<?= e($u['statut']) ?>"><?= $u['statut'] === 'actif' ? 'Actif' : 'Désactivé' ?></span></td>
                            <td class="col-actions">
                                <div class="actions">
                                    <a href="<?= url('/utilisateurs/' . (int) $u['id_utilisateur'] . '/modifier') ?>" class="btn btn-ghost btn-icon btn-sm" title="Modifier" aria-label="Modifier <?= e($u['nom']) ?>"><?= icone('pencil') ?></a>
                                    <?php if (!$estMoi): ?>
                                        <form method="post" action="<?= url('/utilisateurs/' . (int) $u['id_utilisateur'] . '/statut') ?>"
                                              data-confirm="<?= e($u['statut'] === 'actif'
                                                  ? $u['nom'] . ' ne pourra plus se connecter. Son historique est conservé.'
                                                  : $u['nom'] . ' pourra de nouveau se connecter.') ?>"
                                              data-confirm-titre="<?= $u['statut'] === 'actif' ? 'Désactiver ce compte ?' : 'Réactiver ce compte ?' ?>"
                                              data-confirm-bouton="<?= $u['statut'] === 'actif' ? 'Désactiver' : 'Réactiver' ?>"
                                              data-confirm-type="<?= $u['statut'] === 'actif' ? 'danger' : 'primary' ?>">
                                            <?= csrf_champ() ?>
                                            <button type="submit" class="btn btn-ghost btn-icon btn-sm" data-no-loading
                                                    title="<?= $u['statut'] === 'actif' ? 'Désactiver' : 'Réactiver' ?>"
                                                    aria-label="<?= $u['statut'] === 'actif' ? 'Désactiver' : 'Réactiver' ?> <?= e($u['nom']) ?>">
                                                <?= icone($u['statut'] === 'actif' ? 'user-x' : 'user-check') ?>
                                            </button>
                                        </form>
                                        <form method="post" action="<?= url('/utilisateurs/' . (int) $u['id_utilisateur'] . '/supprimer') ?>"
                                              data-confirm="Le compte de <?= e($u['nom']) ?> sera supprimé définitivement. S'il possède un historique, il sera seulement désactivé."
                                              data-confirm-titre="Supprimer ce compte ?" data-confirm-bouton="Supprimer">
                                            <?= csrf_champ() ?>
                                            <button type="submit" class="btn btn-ghost btn-icon btn-sm text-danger" data-no-loading title="Supprimer" aria-label="Supprimer <?= e($u['nom']) ?>"><?= icone('trash') ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= View::partiel('pagination', ['pagination' => $pagination, 'libelle' => 'utilisateurs']) ?>
</section>
