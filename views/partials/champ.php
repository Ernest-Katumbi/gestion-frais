<?php
/**
 * Champ de formulaire standard : libellé au-dessus, aide et message d'erreur en dessous.
 * @var string $nom       nom du champ
 * @var string $libelle
 * @var string $type      text, email, tel, password, date, number, select, textarea
 * @var string $valeur    valeur initiale (l'ancienne saisie est prioritaire)
 * @var bool   $requis
 * @var string $aide      texte d'aide facultatif
 * @var array  $options   pour les listes : valeur => libellé
 * @var string $attributs attributs HTML supplémentaires (déjà sûrs)
 * @var string $classe    classe du bloc (ex. span-2)
 * @var string $icone     icône affichée dans le champ
 */
$type ??= 'text';
$requis ??= false;
$erreurChamp = erreur($nom);
$valeurAffichee = $type === 'password' ? '' : old($nom, $valeur ?? '');
$id = $id ?? $nom;
$aideId = !empty($aide) ? $id . '-aide' : null;
$communs = sprintf(
    'id="%s" name="%s"%s%s%s %s',
    e($id),
    e($nom),
    $requis ? ' required' : '',
    $erreurChamp ? ' aria-invalid="true"' : '',
    $aideId ? ' aria-describedby="' . e($aideId) . '"' : '',
    $attributs ?? ''
);
?>
<div class="field<?= $erreurChamp ? ' has-error' : '' ?><?= !empty($classe) ? ' ' . e($classe) : '' ?>">
    <label for="<?= e($id) ?>"><?= e($libelle) ?><?= $requis ? '<span class="required" aria-hidden="true">*</span>' : '' ?></label>

    <?php if ($type === 'select'): ?>
        <select <?= $communs ?>>
            <?php foreach ($options ?? [] as $cle => $texte): ?>
                <option value="<?= e($cle) ?>"<?= (string) $cle === $valeurAffichee ? ' selected' : '' ?>><?= e($texte) ?></option>
            <?php endforeach; ?>
        </select>
    <?php elseif ($type === 'textarea'): ?>
        <textarea <?= $communs ?>><?= e($valeurAffichee) ?></textarea>
    <?php elseif ($type === 'password'): ?>
        <div class="input-icon">
            <?= icone('lock') ?>
            <input type="password" <?= $communs ?>>
            <button type="button" class="icon-btn toggle-password" data-toggle-password="<?= e($id) ?>" aria-label="Afficher le mot de passe">
                <span data-eye><?= icone('eye') ?></span><span data-eye-off class="hidden"><?= icone('eye-off') ?></span>
            </button>
        </div>
    <?php elseif (!empty($icone)): ?>
        <div class="input-icon">
            <?= icone($icone) ?>
            <input type="<?= e($type) ?>" value="<?= e($valeurAffichee) ?>" <?= $communs ?>>
        </div>
    <?php else: ?>
        <input type="<?= e($type) ?>" value="<?= e($valeurAffichee) ?>" <?= $communs ?>>
    <?php endif; ?>

    <?php if (!empty($aide)): ?><span class="hint" id="<?= e($aideId) ?>"><?= e($aide) ?></span><?php endif; ?>
    <?php if ($erreurChamp): ?><span class="error"><?= icone('alert-circle') ?><?= e($erreurChamp) ?></span><?php endif; ?>
</div>
