<?php /** Modale de confirmation, utilisée par les formulaires portant l'attribut data-confirm. */ ?>
<div class="modal" id="modal-confirm" role="dialog" aria-modal="true" aria-labelledby="modal-confirm-titre" aria-hidden="true">
    <div class="modal__dialog">
        <span class="modal__icon"><?= icone('alert-triangle') ?></span>
        <h2 class="modal__title" id="modal-confirm-titre" data-modal-title>Confirmer l'action</h2>
        <p class="modal__text" data-modal-text></p>
        <div class="modal__actions">
            <button type="button" class="btn btn-secondary" data-modal-cancel>Annuler</button>
            <button type="button" class="btn btn-danger" data-modal-confirm>Confirmer</button>
        </div>
    </div>
</div>
