# Cahier de tests — Gestion des frais scolaires (Institut des Oliviers)

Ce document décrit, pour chaque cas de test **CT01 à CT15**, la procédure à suivre dans le
prototype et le résultat attendu. La colonne « Résultat obtenu » est à remplir lors de
l'exécution (capture d'écran conseillée pour le mémoire).

## Préparation

1. Démarrer **Apache** et **MySQL** dans le panneau de contrôle XAMPP.
2. Réinitialiser les données de démonstration : `php database/seed.php` (≈ 1 minute : les
   reçus PDF sont générés). Les dates sont calculées par rapport au jour de l'installation.
3. Ouvrir <http://localhost/gestion-frais/public/>.

| Compte | E-mail | Mot de passe |
|---|---|---|
| Administrateur | `admin@oliviers.cd` | `Demo@2026` |
| Comptable | `comptable@oliviers.cd` | `Demo@2026` |
| Parent (2 enfants : Merveille et Glody Kabongo) | `parent@oliviers.cd` | `Demo@2026` |
| Autre parent | `e.mujinga@oliviers.cd` | `Demo@2026` |

> Sur la page de connexion, le lien « Comptes de démonstration » remplit l'e-mail et le mot
> de passe d'un clic.

---

## Récapitulatif

| N° | Cas de test | Acteur | Résultat attendu (résumé) | Résultat obtenu |
|---|---|---|---|---|
| CT01 | Connexion valide | Tous | Accès à l'espace du rôle | ☐ Conforme ☐ Non conforme |
| CT02 | Mot de passe erroné | Tous | Message d'erreur, blocage après 5 échecs | ☐ Conforme ☐ Non conforme |
| CT03 | Parent sur une page admin | Parent | Page 403 « Accès refusé » | ☐ Conforme ☐ Non conforme |
| CT04 | Matricule existant | Admin | Enregistrement refusé, message sous le champ | ☐ Conforme ☐ Non conforme |
| CT05 | Élève dont le parent n'a pas de compte | Admin | Compte parent créé, mot de passe provisoire | ☐ Conforme ☐ Non conforme |
| CT06 | Affectation d'une catégorie à une classe | Comptable | Un frais « impayé » par élève, sans doublon | ☐ Conforme ☐ Non conforme |
| CT07 | Paiement partiel au guichet | Comptable | Statut « partiel », reçu PDF, notification | ☐ Conforme ☐ Non conforme |
| CT07 bis | Paiement en dollars au taux du jour | Comptable | Conversion en FC, taux conservé sur le reçu | ☐ Conforme ☐ Non conforme |
| CT08 | Versement supérieur au reste | Comptable | Refus, frais inchangé | ☐ Conforme ☐ Non conforme |
| CT09 | Mobile Money confirmé | Parent | Paiement réussi, reçu, mise à jour en direct | ☐ Conforme ☐ Non conforme |
| CT10 | Solde insuffisant | Parent | Paiement échoué, motif affiché, frais inchangé | ☐ Conforme ☐ Non conforme |
| CT11 | Notification à signature invalide | Système | HTTP 401, aucune modification | ☐ Conforme ☐ Non conforme |
| CT12 | Même notification envoyée deux fois | Système | Traitée une seule fois (un seul reçu) | ☐ Conforme ☐ Non conforme |
| CT13 | Scan du QR d'un reçu | Public | « Reçu authentique » ; falsifié → « Reçu invalide » | ☐ Conforme ☐ Non conforme |
| CT14 | Rappel d'échéance (cron) | Système | Notifications créées, sans doublon le même jour | ☐ Conforme ☐ Non conforme |
| CT15 | Export PDF du rapport mensuel | Comptable | PDF A4 avec indicateurs, graphique, ventilations | ☐ Conforme ☐ Non conforme |

---

## CT01 — Connexion valide

- **Objectif** : un utilisateur autorisé accède à son espace.
- **Étapes** :
  1. Ouvrir la page de connexion.
  2. Saisir `comptable@oliviers.cd` / `Demo@2026`, cliquer sur **Se connecter**.
  3. Se déconnecter (menu utilisateur en haut à droite) et recommencer avec `admin@oliviers.cd`
     puis `parent@oliviers.cd`.
- **Résultat attendu** : message « Bienvenue, … ! » ; le comptable arrive sur son tableau de bord
  (`/comptable`), l'administrateur sur `/admin`, le parent sur `/parent`. La barre latérale ne
  propose que les menus du rôle.

## CT02 — Mot de passe erroné

- **Objectif** : refuser un mot de passe incorrect et limiter les tentatives.
- **Étapes** :
  1. Saisir `comptable@oliviers.cd` avec le mot de passe `faux123`.
  2. Répéter jusqu'à 5 échecs, puis essayer avec le bon mot de passe.
- **Résultat attendu** :
  - « E-mail ou mot de passe incorrect. » ; à partir du 3ᵉ échec, le nombre de tentatives restantes est indiqué ;
  - au 5ᵉ échec : « Trop de tentatives échouées. Réessayez dans 5 min. » ;
  - même le bon mot de passe est refusé pendant 5 minutes ; l'e-mail saisi est conservé.

## CT03 — Parent qui accède à une page admin

- **Objectif** : contrôle d'accès par rôle.
- **Étapes** : se connecter en `parent@oliviers.cd`, puis saisir dans la barre d'adresse
  `…/public/utilisateurs` (puis `…/public/eleves`, `…/public/rapports`).
- **Résultat attendu** : page **403 — Accès refusé** propre (« Cette page est réservée à un autre
  profil d'utilisateur »), avec un bouton de retour à l'accueil. Aucune donnée n'est affichée.
- **Variante (contrôle de propriété)** : connecté en `e.mujinga@oliviers.cd`, ouvrir
  `…/public/parent/frais/71` (frais d'un enfant Kabongo) → 403.

## CT04 — Matricule existant

- **Objectif** : unicité du matricule.
- **Étapes** : en administrateur, **Élèves → Inscrire un élève** ; saisir le matricule
  `IO-2026-001` (déjà attribué), un nom, un prénom, une classe et l'e-mail `parent@oliviers.cd` ;
  cliquer sur **Inscrire l'élève**.
- **Résultat attendu** : l'élève n'est pas créé ; sous le champ Matricule : « Ce matricule est déjà
  attribué à un autre élève. » ; les autres champs restent remplis.

## CT05 — Élève dont le parent n'a pas de compte

- **Objectif** : création automatique du compte parent (règle 10).
- **Étapes** :
  1. **Élèves → Inscrire un élève** ; garder le matricule proposé (ex. `IO-2026-025`).
  2. Nom `Kanku`, prénom `Bienvenu`, classe `1re Orientation`.
  3. E-mail du parent : `marcel.kanku@exemple.cd` → le formulaire indique « Aucun compte avec
     cette adresse » et affiche les champs du parent ; saisir `Marcel Kanku`, `+243 97 888 1122`.
  4. Cliquer sur **Inscrire l'élève**.
- **Résultat attendu** : la fiche de l'élève s'ouvre avec « Un compte parent a été créé pour Marcel
  Kanku » et un encadré affichant **une seule fois** le mot de passe provisoire (bouton Copier) ;
  l'e-mail de bienvenue figure dans `storage/logs/mail.log` ; le compte apparaît dans
  **Utilisateurs** (rôle Parent). Inscrire ensuite un 2ᵉ enfant avec le même e-mail : il est
  rattaché au même compte (« Compte existant : Marcel Kanku »).

## CT06 — Affectation d'une catégorie à une classe

- **Objectif** : `FraisController::assigner()` crée un frais par élève, sans doublon.
- **Étapes** :
  1. En comptable, **Catégories de frais → Nouvelle catégorie** : libellé `Frais de bulletin`,
     code `BULL`, périodicité Annuelle, montant `35000` (FC).
  2. **Affectation des frais** : catégorie « Frais de bulletin », une échéance, cocher
     `3e Scientifique`, **Affecter les frais**, confirmer.
  3. Refaire exactement la même affectation.
- **Résultat attendu** : « 4 frais créés de 35 000 FC chacun » ; la ligne apparaît dans
  « Affectations en cours » (recouvrement 0 %) ; la 2ᵉ fois : « Ces frais étaient déjà affectés…
  aucun doublon n'a été créé ». La catégorie ne peut plus être supprimée (elle est utilisée).

## CT07 — Paiement partiel au guichet

- **Objectif** : encaissement en espèces avec reçu.
- **Étapes** : en comptable, **Encaisser au guichet** ; rechercher `Glody` ; choisir « Minerval
  1er trimestre » (reste 450 000 FC) ; devise **Franc congolais**, saisir `180000` (l'aperçu indique
  « il restera 270 000 FC ») ;
  **Enregistrer le paiement** et confirmer.
- **Résultat attendu** : page du paiement « Paiement réussi 180 000 FC », statut du frais
  **Partiel** (180 000 / 450 000), reçu `REC-2026-…` téléchargeable (PDF A5 avec QR code, « Encaissé par
  Patrick Ilunga », reste 270 000 FC). Côté parent : notification sous la cloche et reçu dans
  **Historique des paiements** ; e-mail dans `mail.log`.

## CT07 bis — Paiement en dollars au taux du jour

- **Objectif** : un versement en USD est converti en FC au taux en vigueur.
- **Étapes** :
  1. En comptable, **Taux de change** : saisir `2860` → « Nouveau taux en vigueur : 1 USD = 2 860 FC » ;
     l'ancien taux reste dans l'historique. (Une variation de plus de 20 % demande une confirmation.)
  2. **Encaisser au guichet**, Glody, « Frais d'examen » (reste 120 000 FC), devise **Dollar américain**.
- **Résultat attendu** : montant proposé 41,96 USD ; aperçu « 41,96 USD × 2 860 = 120 000 FC (arrondi au
  reste) » ; après validation, frais **Payé**, paiement enregistré « 41,96 USD (120 000 FC) » avec le taux
  appliqué, visible sur le reçu (« Versé 41,96 USD · Taux 1 USD = 2 860 FC ») et dans le rapport
  (ventilation « Par devise de versement »).

## CT08 — Versement supérieur au reste

- **Objectif** : on ne peut jamais payer plus que le reste.
- **Étapes** : sur le même frais (reste 270 000 FC), saisir `270001` et valider.
- **Résultat attendu** : le navigateur signale « Le montant dépasse le reste à payer » ; si le
  contrôle du navigateur est contourné, le serveur refuse : « Le montant versé (270 001 FC) dépasse
  le reste à payer (270 000 FC). » Le frais et la liste des paiements sont inchangés.

## CT09 — Mobile Money confirmé

- **Objectif** : paiement en ligne via la passerelle (simulateur).
- **Étapes** :
  1. En `parent@oliviers.cd`, carte « Minerval 1er trimestre » de Merveille (reste 150 000 FC) →
     **Payer** ; montant proposé 150 000 FC ; Mobile Money, M-Pesa, numéro prérempli → **Payer maintenant**.
  2. La page affiche « En attente de confirmation… » ; cliquer sur **Ouvrir mon téléphone
     (simulateur)**.
  3. Sur le téléphone simulé, saisir un code PIN à 4 chiffres, **Confirmer**.
  4. Revenir sur l'onglet de l'application sans recharger.
- **Résultat attendu** : le téléphone affiche « Paiement confirmé » ; en moins de 3 secondes,
  l'application passe seule à « Paiement confirmé » avec **Télécharger le reçu** ; le frais est
  **Payé** ; notification reçue ; paiement visible par le comptable (mode Mobile Money, sans
  comptable).

## CT10 — Solde insuffisant

- **Étapes** : en parent, **Payer** un frais de Glody en Mobile Money ; sur le téléphone simulé,
  cliquer sur **Solde insuffisant**.
- **Résultat attendu** : l'application affiche « Le paiement n'a pas abouti — Motif : Solde
  insuffisant. Aucun montant n'a été débité. » avec **Réessayer** ; le paiement est « Échoué » ;
  le frais est inchangé ; une notification d'échec est créée.
- **Variante carte** : moyen Carte bancaire, numéro `4000 0000 0000 0002` → « Carte refusée par la
  banque émettrice ».

## CT11 — Notification à signature invalide

- **Objectif** : `callback.php` rejette une notification falsifiée.
- **Étapes** :
  1. En parent, lancer un paiement Mobile Money **sans** le confirmer sur le téléphone.
  2. Dans un terminal, à la racine du projet : `php tests/envoyer_callback.php` (liste les
     références en attente), puis
     `php tests/envoyer_callback.php PAY-AAAAMMJJ-XXXXXX --falsifie`.
- **Résultat attendu** : `HTTP 401 {"message":"Signature invalide."}` ; « Après » identique à
  « Avant » (paiement en attente, frais inchangé, aucun reçu) ; la tentative est tracée dans
  `storage/logs/app.log`.

## CT12 — Même notification envoyée deux fois

- **Étapes** : sur la même référence : `php tests/envoyer_callback.php PAY-AAAAMMJJ-XXXXXX --fois=2`.
- **Résultat attendu** : envoi 1 → `HTTP 200 Notification traitée.` ; envoi 2 → `HTTP 200
  Notification déjà traitée.` ; un seul reçu, une seule notification, versement compté une seule
  fois. Un `FAILED` envoyé ensuite est lui aussi ignoré.

## CT13 — Scan du QR d'un reçu

- **Étapes** :
  1. Ouvrir un reçu PDF (ex. celui de CT07) et scanner le QR code avec un téléphone
     (ou, en comptable, bouton **Vérifier ce reçu** sur la page du paiement).
  2. Modifier ensuite un caractère du paramètre `h=` (ou le numéro `n=`) dans l'adresse.
- **Résultat attendu** : « Reçu authentique » avec numéro, date, élève, classe, frais, montant et
  mode (sans coordonnées du parent) ; adresse modifiée → « Reçu invalide » (HTTP 404).
- **Remarque** : pour scanner depuis un téléphone, celui-ci doit joindre le serveur : en local,
  remplacer `localhost` par l'adresse IP du PC dans `APP_URL` (config.php) puis régénérer les
  données ; en ligne, le QR pointe directement vers l'adresse publique.

## CT14 — Rappel d'échéance (cron)

- **Étapes** : dans un terminal, `php cron/rappels_echeances.php`, puis relancer la même commande.
- **Résultat attendu** : 1ʳᵉ exécution : liste des rappels envoyés (Minerval 1er trimestre, échéance
  dans 3 jours) et « … rappels envoyés » ; 2ᵉ exécution : « 0 rappel envoyé, … déjà envoyés
  aujourd'hui ». Côté parent : notification « Rappel : « Minerval 1er trimestre » de … arrive à
  échéance le … » sous la cloche ; e-mails dans `mail.log`.
- **Option** : `php cron/rappels_echeances.php --date=AAAA-MM-JJ --jours=7` simule un autre jour.

## CT15 — Export PDF du rapport mensuel

- **Étapes** : en comptable, **Rapports** → période **Mois**, mois en cours → **Afficher**, puis
  **Exporter en PDF**.
- **Résultat attendu** : PDF A4 avec logo, période, 4 indicateurs (total encaissé, total impayé,
  taux de recouvrement, part des paiements en ligne), graphique des encaissements par jour,
  ventilations par catégorie, classe et mode (chacune totalisant le « Total encaissé »), reste à
  recouvrer par classe. Les montants concordent avec l'écran et avec la liste des paiements
  filtrée sur le même mois (statut Réussi).

---

## Tests automatisés (PHPUnit)

```bash
vendor/bin/phpunit
```

Ils utilisent une base dédiée `gestion_frais_test` (recréée à chaque test) et n'affectent jamais
les données de démonstration.

| Fichier | Ce qui est vérifié |
|---|---|
| `tests/FraisTest.php` | statuts impayé / partiel / payé, calcul en centimes, versement nul ou négatif refusé, dépassement du reste refusé, mise à jour atomique, affectation sans doublon |
| `tests/PaiementTest.php` | format `PAY-AAAAMMJJ-XXXXXX`, date du paiement, unicité (1 000 références), référence en double refusée par la base |
| `tests/MonnaieTest.php` | franc par défaut, formatage (450 000 FC, 150,00 USD), conversion au taux du jour, tolérance d'arrondi, refus sans taux ou devise inconnue, historique des taux |
| `tests/PasserelleTest.php` | signature HMAC valide / invalide, `callback.php` exécuté réellement : signature falsifiée (401), notification reçue deux fois (traitée une fois), échec (frais inchangé), référence inconnue (404) |

Résultat attendu : `OK (34 tests, 78 assertions)`.
