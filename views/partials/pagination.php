<?php
/**
 * Pagination : conserve les filtres de la page (paramètres GET).
 * @var array{page:int, parPage:int, total:int} $pagination
 * @var string $libelle ex. « utilisateurs »
 */
$page = $pagination['page'];
$total = $pagination['total'];
$pages = max(1, (int) ceil($total / $pagination['parPage']));
$debut = $total === 0 ? 0 : ($page - 1) * $pagination['parPage'] + 1;
$fin = min($total, $page * $pagination['parPage']);
$lien = static fn(int $p): string => url(cheminCourant(), array_merge($_GET, ['page' => $p]));

// Fenêtre de 5 numéros autour de la page courante.
$premier = max(1, min($page - 2, $pages - 4));
$dernier = min($pages, $premier + 4);
?>
<div class="pagination">
    <span><?= $debut ?>–<?= $fin ?> sur <?= $total ?> <?= e($libelle ?? 'éléments') ?></span>
    <?php if ($pages > 1): ?>
        <nav class="pagination__pages" aria-label="Pagination">
            <a class="page-link<?= $page <= 1 ? ' is-disabled' : '' ?>" href="<?= e($lien($page - 1)) ?>" aria-label="Page précédente"><?= icone('chevron-left', 'icon-sm') ?></a>
            <?php for ($p = $premier; $p <= $dernier; $p++): ?>
                <a class="page-link<?= $p === $page ? ' is-current' : '' ?>" href="<?= e($lien($p)) ?>"<?= $p === $page ? ' aria-current="page"' : '' ?>><?= $p ?></a>
            <?php endfor; ?>
            <a class="page-link<?= $page >= $pages ? ' is-disabled' : '' ?>" href="<?= e($lien($page + 1)) ?>" aria-label="Page suivante"><?= icone('chevron-right', 'icon-sm') ?></a>
        </nav>
    <?php endif; ?>
</div>
