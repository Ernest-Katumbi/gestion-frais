/* =============================================================================
   Institut des Oliviers — comportements de l'interface (JavaScript natif)
   Menu mobile, menus déroulants, modales de confirmation, toasts,
   affichage du mot de passe, validation des formulaires.
   ============================================================================= */
(function () {
  'use strict';

  // --- Barre latérale (mobile) ------------------------------------------------
  document.querySelectorAll('[data-sidebar-toggle]').forEach(function (bouton) {
    bouton.addEventListener('click', function () {
      var ouvert = document.body.classList.toggle('sidebar-open');
      bouton.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
    });
  });
  var fond = document.querySelector('.sidebar-backdrop');
  if (fond) {
    fond.addEventListener('click', function () { document.body.classList.remove('sidebar-open'); });
  }

  // --- Menus déroulants -------------------------------------------------------
  function fermerMenus(sauf) {
    document.querySelectorAll('.dropdown.is-open').forEach(function (d) {
      if (d !== sauf) {
        d.classList.remove('is-open');
        var b = d.querySelector('[data-dropdown-toggle]');
        if (b) b.setAttribute('aria-expanded', 'false');
      }
    });
  }
  document.querySelectorAll('[data-dropdown-toggle]').forEach(function (bouton) {
    bouton.addEventListener('click', function (e) {
      e.stopPropagation();
      var menu = bouton.closest('.dropdown');
      fermerMenus(menu);
      var ouvert = menu.classList.toggle('is-open');
      bouton.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
    });
  });
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.dropdown__menu')) fermerMenus(null);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      fermerMenus(null);
      fermerModale();
      document.body.classList.remove('sidebar-open');
    }
  });

  // --- Toasts -----------------------------------------------------------------
  function retirerToast(toast) {
    if (!toast || toast.classList.contains('is-leaving')) return;
    toast.classList.add('is-leaving');
    setTimeout(function () { toast.remove(); }, 260);
  }
  document.querySelectorAll('.toast').forEach(function (toast, i) {
    var bouton = toast.querySelector('.toast__close');
    if (bouton) bouton.addEventListener('click', function () { retirerToast(toast); });
    // Les erreurs restent plus longtemps à l'écran que les confirmations.
    var duree = toast.classList.contains('toast-erreur') ? 9000 : 5500;
    setTimeout(function () { retirerToast(toast); }, duree + i * 400);
  });

  // --- Modale de confirmation -------------------------------------------------
  // Usage : <form data-confirm="Message" data-confirm-titre="Titre" data-confirm-bouton="Supprimer">
  var modale = document.getElementById('modal-confirm');
  var formulaireEnAttente = null;

  function ouvrirModale(form) {
    if (!modale) return false;
    formulaireEnAttente = form;
    modale.querySelector('[data-modal-title]').textContent = form.dataset.confirmTitre || 'Confirmer l’action';
    modale.querySelector('[data-modal-text]').textContent = form.dataset.confirm;
    var ok = modale.querySelector('[data-modal-confirm]');
    ok.textContent = form.dataset.confirmBouton || 'Confirmer';
    var danger = form.dataset.confirmType !== 'primary';
    ok.className = 'btn ' + (danger ? 'btn-danger' : 'btn-primary');
    modale.querySelector('.modal__icon').className = 'modal__icon' + (danger ? '' : ' is-primary');
    modale.classList.add('is-open');
    modale.setAttribute('aria-hidden', 'false');
    setTimeout(function () { modale.querySelector('[data-modal-cancel]').focus(); }, 50);
    return true;
  }
  function fermerModale() {
    if (!modale) return;
    modale.classList.remove('is-open');
    modale.setAttribute('aria-hidden', 'true');
    formulaireEnAttente = null;
  }
  if (modale) {
    modale.querySelector('[data-modal-cancel]').addEventListener('click', fermerModale);
    modale.addEventListener('click', function (e) { if (e.target === modale) fermerModale(); });
    modale.querySelector('[data-modal-confirm]').addEventListener('click', function () {
      var form = formulaireEnAttente;
      if (!form) return;
      form.dataset.confirmed = '1';
      fermerModale();
      if (form.requestSubmit) form.requestSubmit(); else form.submit();
    });
  }

  // --- Validation côté navigateur (complément de la validation serveur) ------
  var messages = {
    valueMissing: 'Ce champ est obligatoire.',
    typeMismatch: { email: 'Adresse e-mail invalide.', defaut: 'Valeur invalide.' },
    tooShort: function (c) { return 'Au moins ' + c.minLength + ' caractères.'; },
    tooLong: function (c) { return 'Au plus ' + c.maxLength + ' caractères.'; },
    rangeUnderflow: function (c) { return 'La valeur minimale est ' + c.min + '.'; },
    rangeOverflow: function (c) { return 'La valeur maximale est ' + c.max + '.'; },
    stepMismatch: 'Valeur invalide (2 décimales au plus).',
    badInput: 'Valeur invalide.'
  };

  function messagePour(champ) {
    var v = champ.validity;
    if (v.valueMissing) return messages.valueMissing;
    if (v.typeMismatch) return champ.type === 'email' ? messages.typeMismatch.email : messages.typeMismatch.defaut;
    if (v.tooShort) return messages.tooShort(champ);
    if (v.tooLong) return messages.tooLong(champ);
    if (v.rangeUnderflow) return messages.rangeUnderflow(champ);
    if (v.rangeOverflow) return messages.rangeOverflow(champ);
    if (v.stepMismatch) return messages.stepMismatch;
    if (v.patternMismatch) return champ.dataset.patternMessage || 'Format invalide.';
    if (v.customError) return champ.validationMessage;
    return messages.badInput;
  }

  function afficherErreur(champ, message) {
    var bloc = champ.closest('.field');
    if (!bloc) return;
    var erreur = bloc.querySelector('.error[data-js]');
    var serveur = bloc.querySelector('.error:not([data-js])');
    if (serveur) serveur.remove();
    if (message) {
      if (!erreur) {
        erreur = document.createElement('span');
        erreur.className = 'error';
        erreur.setAttribute('data-js', '');
        bloc.appendChild(erreur);
      }
      erreur.textContent = message;
      bloc.classList.add('has-error');
      champ.setAttribute('aria-invalid', 'true');
    } else {
      if (erreur) erreur.remove();
      bloc.classList.remove('has-error');
      champ.removeAttribute('aria-invalid');
    }
  }

  function verifierConfirmation(champ) {
    var cible = champ.dataset.match && document.getElementById(champ.dataset.match);
    if (cible) {
      champ.setCustomValidity(champ.value !== cible.value ? 'Les deux mots de passe ne correspondent pas.' : '');
    }
  }

  document.querySelectorAll('form[data-validate]').forEach(function (form) {
    form.setAttribute('novalidate', '');
    form.querySelectorAll('input, select, textarea').forEach(function (champ) {
      champ.addEventListener('blur', function () {
        verifierConfirmation(champ);
        if (champ.value !== '' || champ.dataset.touched) afficherErreur(champ, champ.checkValidity() ? '' : messagePour(champ));
        champ.dataset.touched = '1';
      });
      champ.addEventListener('input', function () {
        verifierConfirmation(champ);
        if (champ.closest('.field.has-error') && champ.checkValidity()) afficherErreur(champ, '');
      });
    });
  });

  // --- Soumission : validation, confirmation, état « chargement » -----------
  document.addEventListener('submit', function (e) {
    var form = e.target;

    if (form.hasAttribute('data-validate')) {
      var premier = null;
      form.querySelectorAll('input, select, textarea').forEach(function (champ) {
        verifierConfirmation(champ);
        var ok = champ.checkValidity();
        afficherErreur(champ, ok ? '' : messagePour(champ));
        if (!ok && !premier) premier = champ;
      });
      if (premier) {
        e.preventDefault();
        premier.focus();
        return;
      }
    }

    if (form.dataset.confirm && form.dataset.confirmed !== '1') {
      if (ouvrirModale(form)) {
        e.preventDefault();
        return;
      }
    }
    form.dataset.confirmed = '';

    var bouton = form.querySelector('button[type="submit"]:not([data-no-loading])');
    if (bouton) {
      // Empêche le double envoi (ex. double clic sur « Enregistrer le paiement »).
      setTimeout(function () {
        bouton.classList.add('is-loading');
        bouton.disabled = true;
      }, 0);
    }
  });

  // Lorsqu'on revient sur la page avec le bouton « Précédent », on réactive les boutons.
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) {
      document.querySelectorAll('.btn.is-loading').forEach(function (b) {
        b.classList.remove('is-loading');
        b.disabled = false;
      });
    }
  });

  // --- Afficher / masquer le mot de passe -------------------------------------
  document.querySelectorAll('[data-toggle-password]').forEach(function (bouton) {
    bouton.addEventListener('click', function () {
      var champ = document.getElementById(bouton.dataset.togglePassword);
      var visible = champ.type === 'text';
      champ.type = visible ? 'password' : 'text';
      bouton.setAttribute('aria-label', visible ? 'Afficher le mot de passe' : 'Masquer le mot de passe');
      bouton.querySelector('[data-eye]').classList.toggle('hidden', !visible);
      bouton.querySelector('[data-eye-off]').classList.toggle('hidden', visible);
    });
  });

  // --- Copier dans le presse-papiers ------------------------------------------
  document.querySelectorAll('[data-copy]').forEach(function (bouton) {
    bouton.addEventListener('click', function () {
      var texte = bouton.dataset.copy;
      var origine = Array.prototype.map.call(bouton.childNodes, function (n) { return n.cloneNode(true); });
      var fini = function () {
        bouton.textContent = 'Copié !';
        setTimeout(function () { bouton.replaceChildren.apply(bouton, origine); }, 1500);
      };
      if (navigator.clipboard) navigator.clipboard.writeText(texte).then(fini, fini);
    });
  });

  // --- Comptes de démonstration (page de connexion) ---------------------------
  document.querySelectorAll('[data-demo-email]').forEach(function (bouton) {
    bouton.addEventListener('click', function () {
      document.getElementById('email').value = bouton.dataset.demoEmail;
      document.getElementById('mot_de_passe').value = bouton.dataset.demoPassword;
      document.getElementById('mot_de_passe').focus();
    });
  });

  // --- Inscription d'un élève : recherche du compte parent --------------------
  // Indique si l'e-mail saisi correspond à un compte existant (rattachement)
  // ou si un nouveau compte parent sera créé (champs nom / téléphone / adresse).
  document.querySelectorAll('[data-parent-lookup]').forEach(function (bloc) {
    var champ = bloc.querySelector('input[name="email_parent"]');
    var statut = bloc.querySelector('[data-parent-status]');
    var nouveau = bloc.querySelector('[data-parent-nouveau]');
    var nomParent = bloc.querySelector('input[name="nom_parent"]');
    var minuterie = null;
    var derniere = null;

    // Message construit avec textContent : aucune donnée n'est interprétée comme du HTML.
    function afficher(type, icone, titre, details) {
      statut.className = 'span-2 parent-status is-' + type;
      statut.replaceChildren();
      var modele = document.querySelector('template[data-icone="' + icone + '"]');
      if (modele) statut.appendChild(modele.content.cloneNode(true));
      var texte = document.createElement('div');
      var fort = document.createElement('strong');
      fort.textContent = titre;
      texte.appendChild(fort);
      details.forEach(function (ligne) {
        texte.appendChild(document.createElement('br'));
        texte.appendChild(document.createTextNode(ligne));
      });
      statut.appendChild(texte);
    }
    function basculerNouveau(visible) {
      nouveau.classList.toggle('hidden', !visible);
      nomParent.required = visible;
      // Astérisque « obligatoire » sur le libellé du nom du parent.
      var libelle = bloc.querySelector('label[for="nom_parent"]');
      var etoile = libelle.querySelector('.required');
      if (visible && !etoile) {
        etoile = document.createElement('span');
        etoile.className = 'required';
        etoile.setAttribute('aria-hidden', 'true');
        etoile.textContent = '*';
        libelle.appendChild(etoile);
      } else if (!visible && etoile) {
        etoile.remove();
      }
      if (!visible) {
        nouveau.querySelectorAll('input').forEach(function (i) { i.setCustomValidity(''); });
      }
    }

    function verifier() {
      var email = champ.value.trim().toLowerCase();
      if (email === derniere) return;
      derniere = email;
      if (!champ.checkValidity() || email === '') {
        statut.classList.add('hidden');
        basculerNouveau(true);
        return;
      }
      fetch(bloc.dataset.parentLookup + '?email=' + encodeURIComponent(email), {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (email !== champ.value.trim().toLowerCase() || !d.valide) return;
          if (!d.existe) {
            afficher('nouveau', 'user-plus', 'Aucun compte avec cette adresse.',
              ['Un compte parent sera créé : complétez les informations ci-dessous.']);
            basculerNouveau(true);
          } else if (d.role !== 'parent') {
            afficher('erreur', 'alert-circle', 'Adresse déjà utilisée par un compte du personnel.',
              ['Saisissez l’e-mail du parent.']);
            basculerNouveau(false);
          } else if (!d.actif) {
            afficher('erreur', 'alert-circle', 'Le compte de ' + d.nom + ' est désactivé.',
              ['Réactivez-le d’abord dans « Utilisateurs ».']);
            basculerNouveau(false);
          } else {
            var infos = [];
            if (d.telephone) infos.push(d.telephone);
            if (d.enfants > 0) infos.push(d.enfants + ' enfant' + (d.enfants > 1 ? 's' : '') + ' déjà rattaché' + (d.enfants > 1 ? 's' : ''));
            afficher('existant', 'user-check', 'Compte existant : ' + d.nom,
              (infos.length ? [infos.join(' · ')] : []).concat(['L’élève sera rattaché à ce compte.']));
            basculerNouveau(false);
          }
        })
        .catch(function () { statut.classList.add('hidden'); basculerNouveau(true); });
    }

    champ.addEventListener('input', function () {
      clearTimeout(minuterie);
      minuterie = setTimeout(verifier, 450);
    });
    champ.addEventListener('change', verifier);
    if (champ.value.trim() !== '') verifier();
  });

  // --- Filtres : soumission automatique à la sélection -------------------------
  document.querySelectorAll('[data-autosubmit]').forEach(function (champ) {
    champ.addEventListener('change', function () { champ.form.submit(); });
  });
})();
