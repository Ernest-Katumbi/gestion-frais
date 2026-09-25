<?php /** @var ?Throwable $exception détail affiché uniquement si APP_DEBUG est actif */ ?>
<div class="card error-page">
    <div class="card__body">
        <span class="code">500</span>
        <h1>Une erreur est survenue</h1>
        <p>Le service a rencontré un problème inattendu. L'incident a été enregistré ; veuillez réessayer dans quelques instants.</p>
        <?= View::partiel('erreur_actions') ?>
        <?php if (!empty($exception)): ?>
            <details class="error-trace" open>
                <summary>Détail technique (mode développement)</summary>
                <pre><?= e(get_class($exception) . ' : ' . $exception->getMessage() . "\n" . $exception->getFile() . ':' . $exception->getLine() . "\n\n" . $exception->getTraceAsString()) ?></pre>
            </details>
        <?php endif; ?>
    </div>
</div>
