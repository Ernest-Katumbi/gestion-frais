<?php /** Tableau de bord du comptable (indicateurs financiers ajoutés aux itérations 3 et 5). @var array $utilisateur */ ?>
<section class="card welcome mb-3">
    <div>
        <h2>Bonjour, <?= e($utilisateur['nom']) ?></h2>
        <p>Suivez les encaissements et les impayés de l'<?= e(APP_NOM) ?>.</p>
    </div>
    <img class="welcome__art" src="<?= asset('img/logo.svg') ?>" alt="">
</section>

<section class="card">
    <?= View::partiel('vide', [
        'icone' => 'receipt',
        'titre' => 'Aucun paiement pour l\'instant',
        'texte' => 'Les encaissements, les impayés et les indicateurs de recouvrement s\'afficheront ici dès que les frais auront été affectés aux élèves.',
    ]) ?>
</section>
