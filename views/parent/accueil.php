<?php /** Accueil de l'espace parent (enfants et frais ajoutés à l'itération 3). @var array $utilisateur */ ?>
<section class="card welcome mb-3">
    <div>
        <h2>Bonjour, <?= e($utilisateur['nom']) ?></h2>
        <p>Consultez les frais scolaires de vos enfants et payez en toute sécurité.</p>
    </div>
    <img class="welcome__art" src="<?= asset('img/logo.svg') ?>" alt="">
</section>

<section class="card">
    <?= View::partiel('vide', [
        'icone' => 'graduation-cap',
        'titre' => 'Aucun enfant rattaché pour l\'instant',
        'texte' => 'Dès que l\'Institut aura inscrit vos enfants, leurs frais scolaires apparaîtront ici.',
    ]) ?>
</section>
