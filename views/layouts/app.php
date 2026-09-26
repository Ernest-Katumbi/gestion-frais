<?php
/**
 * Gabarit des espaces authentifiés : barre latérale + barre supérieure.
 * @var string $contenu  HTML de la vue
 * @var string $titre    titre de la page
 * @var array  $fil      fil d'Ariane : [[libellé, chemin|null], …]
 */
$moi = utilisateur();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titre ?? 'Accueil') ?> · <?= e(APP_NOM) ?></title>
    <link rel="icon" href="<?= asset('img/favicon.png') ?>" type="image/png">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<a class="sr-only" href="#contenu">Aller au contenu</a>
<div class="app-shell">
    <?= View::partiel('sidebar', ['moi' => $moi]) ?>
    <div class="sidebar-backdrop" aria-hidden="true"></div>

    <div class="main">
        <?= View::partiel('topbar', ['moi' => $moi, 'titre' => $titre ?? '', 'fil' => $fil ?? []]) ?>

        <main class="content" id="contenu">
            <?= $contenu ?>
        </main>

        <footer class="app-footer">
            © <?= date('Y') ?> <?= e(APP_NOM) ?> · <?= e(APP_VILLE) ?>
        </footer>
    </div>
</div>

<?= View::partiel('toasts') ?>
<?= View::partiel('modal_confirm') ?>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
