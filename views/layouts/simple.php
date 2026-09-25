<?php
/**
 * Gabarit des pages hors espace authentifié : connexion, erreurs.
 * @var string $contenu
 * @var string $titre
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($titre ?? APP_NOM) ?> · <?= e(APP_NOM) ?></title>
    <link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<main class="auth-page">
    <?= $contenu ?>
</main>
<?= View::partiel('toasts') ?>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
