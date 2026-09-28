# Gestion des frais scolaires — Institut des Oliviers

Prototype d'application web de **gestion des frais scolaires avec paiement électronique**
pour l'Institut des Oliviers (école conventionnée adventiste, Kolwezi, RDC), réalisé dans le
cadre d'un mémoire de fin d'études en génie logiciel.

- **Administrateur** : utilisateurs, classes, élèves (création automatique du compte parent), tableau de bord global.
- **Comptable** : catégories de frais, affectation aux classes, encaissement au guichet avec reçu PDF,
  impayés, paiements, rapports par période avec graphique et export PDF, taux de change du jour.
- **Monnaie** : frais et soldes tenus en **francs congolais (FC)** ; paiements acceptés en FC ou en
  **dollars (USD)**, convertis au taux du jour saisi par le comptable (historique conservé ; chaque
  paiement garde le montant versé, la devise et le taux appliqué).
- **Parent** : frais de ses enfants, paiement en ligne (Mobile Money ou carte via la passerelle),
  suivi en direct, reçus, historique, notifications.
- **Passerelle de paiement** : interface `PasserellePaiement` ; simulateur local (téléphone Mobile
  Money, page de carte) qui notifie `public/callback.php` en JSON signé HMAC-SHA256.

**Pile** : PHP 8.1+ natif (MVC, sans framework), MySQL / MariaDB via PDO (requêtes préparées),
HTML / CSS / JavaScript sans CDN. Bibliothèques Composer : Dompdf (PDF), chillerlan/php-qrcode
(QR codes), PHPUnit (tests).

---

## 1. Installation locale sous XAMPP (≈ 5 minutes)

### Prérequis

- **XAMPP 8.2** (PHP 8.2, Apache, MariaDB) — par exemple `winget install ApacheFriends.Xampp.8.2`
- **Composer** — <https://getcomposer.org/>
- Extensions PHP à activer dans `C:\xampp\php\php.ini` (retirer le `;` en début de ligne) :
  `extension=gd`, `extension=zip`, `extension=intl`

### Étapes

1. **Copier le projet** dans `C:\xampp\htdocs\gestion-frais`
   (évitez les dossiers synchronisés par OneDrive : ils peuvent rendre des fichiers indisponibles).
2. **Installer les dépendances** :

   ```bash
   cd C:\xampp\htdocs\gestion-frais
   composer install
   ```

3. **Configurer** : copier `config/config.example.php` en `config/config.php`, puis y adapter
   si besoin les valeurs par défaut :
   - `DB_PORT` (3306 par défaut ; autre valeur si le port est occupé par un autre MySQL) ;
   - `RECU_SECRET` et `PAYMENT_WEBHOOK_SECRET` : remplacer par deux longues chaînes aléatoires,
     par exemple le résultat de `php -r "echo bin2hex(random_bytes(32));"` ;
   - `DEVISE` (devise de tenue des comptes, `CDF` par défaut) et `DEVISE_ETRANGERE` (`USD`) ;
     le taux de change se saisit ensuite dans l'application (menu **Taux de change** du comptable).
4. **Démarrer Apache et MySQL** dans le panneau de contrôle XAMPP.
5. **Créer la base et les données de démonstration** (≈ 1 minute, les reçus PDF sont générés) :

   ```bash
   php database/seed.php
   ```

   Variante phpMyAdmin : créer la base `gestion_frais` (utf8mb4_unicode_ci), importer
   `database/gestion_frais.sql`, puis lancer la commande ci-dessus pour les données.
6. **Ouvrir** <http://localhost/gestion-frais/public/>

> L'URL de base est définie par `APP_URL` dans `config/config.php` ; si l'application est servie
> ailleurs que sous `/gestion-frais/public/`, adaptez aussi `RewriteBase` dans `public/.htaccess`.

### Comptes de démonstration

Mot de passe commun : **`Demo@2026`**

| Rôle | E-mail |
|---|---|
| Administrateur | `admin@oliviers.cd` |
| Comptable | `comptable@oliviers.cd` |
| Parent (2 enfants : Merveille et Glody Kabongo) | `parent@oliviers.cd` |
| Autres parents | `e.mujinga@oliviers.cd`, `d.tshibangu@oliviers.cd`, `c.kasongo@oliviers.cd` |

Les données de démonstration comprennent 6 classes, 24 élèves, 16 familles, 4 catégories de frais
(en FC), 96 frais, 3 taux de change et 49 paiements (guichet et en ligne, en FC et en USD, réussis et
échoués) avec leurs reçus et notifications.
Toutes les dates sont calculées par rapport au jour de l'installation : il y a toujours des
échéances passées, proches (dans 3 jours) et futures.

---

## 2. Commandes utiles

| Action | Commande |
|---|---|
| Réinstaller les données de démonstration (efface tout) | `php database/seed.php` |
| Installer seulement si la base est vide | `php database/seed.php --si-vide` |
| Rappels d'échéance (à planifier chaque jour) | `php cron/rappels_echeances.php` |
| Simuler une notification de la passerelle (CT11, CT12) | `php tests/envoyer_callback.php` |
| Tests automatisés | `vendor/bin/phpunit` |

**Planifier les rappels** avec le Planificateur de tâches Windows (chaque jour à 7 h) :

```bash
schtasks /create /tn "Rappels frais scolaires" /sc daily /st 07:00 /tr "C:\xampp\php\php.exe C:\xampp\htdocs\gestion-frais\cron\rappels_echeances.php"
```

**Tests** : `vendor/bin/phpunit` exécute 34 tests (règles des frais, références de paiement,
conversion des devises, signature et idempotence du callback) sur une base dédiée `gestion_frais_test`, sans toucher aux
données de démonstration. Le cahier de tests manuels **CT01 à CT15** est dans [`TESTS.md`](TESTS.md).

**E-mails** : avec `MAIL_DRIVER = 'log'` (défaut), les e-mails sont écrits dans
`storage/logs/mail.log`. Pour un envoi réel : `composer require phpmailer/phpmailer`, puis
`MAIL_DRIVER = 'smtp'` et les paramètres `SMTP_*`.

---

## 3. Architecture

```
config/       configuration (hors webroot) et table des routes
core/         Database, Router, Session, Csrf, View, Controller, helpers (e(), exigerRole(), passerelle()…)
controllers/  Auth, User, Classe, Eleve, Frais, Paiement, Rapport, Parent, TauxChange
models/       User, Classe, Eleve, CategorieFrais, Frais, Paiement, Recu, Notification, TauxChange
services/     PasserellePaiement (interface), SimulateurPasserelle, PasserelleReelle (squelette),
              GenerateurRecu (PDF + QR), Notificateur (notifications et e-mails), Monnaie (conversion)
views/        layouts, partials, vues par rôle, gabarits PDF (reçu, rapport, impayés)
public/       index.php (contrôleur frontal), callback.php, verifier-recu.php, simulateur/, assets/
cron/         rappels_echeances.php
database/     gestion_frais.sql (schéma), seed.php (données de démonstration)
tests/        PHPUnit + envoyer_callback.php
docker/       configuration Apache et démarrage du conteneur (déploiement)
```

Règles métier clés : `Frais::statutApres()` (statut calculé en centimes, `DomainException` si le
versement est nul ou dépasse le reste), `Frais::enregistrerVersement()` (UPDATE atomique
conditionnel), `FraisController::assigner()` (affectation transactionnelle sans doublon),
`public/callback.php` (signature HMAC, `SELECT … FOR UPDATE`, idempotence, reçu et notification
dans une seule transaction). Seuls les paiements « réussi » comptent dans les soldes et rapports.

**Sécurité** : `password_hash` / `password_verify`, `session_regenerate_id` à la connexion,
cookies `HttpOnly` / `SameSite=Lax` / `Secure` en HTTPS, jeton CSRF sur tous les POST,
échappement systématique (`e()`), requêtes préparées uniquement, contrôle d'accès par rôle et par
propriété (un parent ne voit que ses enfants), blocage 5 minutes après 5 échecs de connexion,
validation serveur de toutes les entrées, aucune donnée de carte stockée, secrets hors webroot,
reçus PDF hors webroot servis après contrôle d'accès, en-têtes CSP / X-Frame-Options,
pages d'erreur 403 / 404 / 500 sans trace en production.

---

## 4. Déploiement en ligne sur Render

L'application est livrée avec un `Dockerfile` (PHP 8.2 + Apache) et un Blueprint `render.yaml`.
Render ne proposant pas MySQL géré, la base est hébergée chez un fournisseur MySQL externe
(exemple ci-dessous avec **Aiven for MySQL**, offre gratuite).

### 4.1 Créer la base MySQL (Aiven)

1. Créer un compte sur <https://console.aiven.io/> puis un service **MySQL**, offre **Free**,
   région en Europe (proche de Render Francfort).
2. Dans l'onglet **Overview** du service, relever : *Host*, *Port*, *User* (`avnadmin`),
   *Password*, *Database name* (`defaultdb`), et télécharger le **CA certificate** (`ca.pem`).

### 4.2 Créer le service sur Render

1. Ouvrir <https://dashboard.render.com/blueprint/new?repo=https://github.com/Ernest-Katumbi/gestion-frais>
   (ou **New → Blueprint** et choisir le dépôt).
2. Renseigner les variables demandées :

   | Variable | Valeur |
   |---|---|
   | `DB_HOST` | hôte Aiven (ex. `mysql-xxxx.aivencloud.com`) |
   | `DB_PORT` | port Aiven (ex. `12345`) |
   | `DB_NAME` | `defaultdb` |
   | `DB_USER` | `avnadmin` |
   | `DB_PASS` | mot de passe Aiven |
   | `DB_SSL_CA_PEM` | contenu complet du fichier `ca.pem` (de `-----BEGIN CERTIFICATE-----` à `-----END CERTIFICATE-----`) |

   `RECU_SECRET` et `PAYMENT_WEBHOOK_SECRET` sont générés automatiquement par Render.
3. Cliquer sur **Apply**. Au premier démarrage, la base vide est installée automatiquement avec les
   données de démonstration (`AUTO_SEED`) ; les reçus PDF sont générés à leur première ouverture.
4. L'application est disponible à l'adresse `https://gestion-frais-oliviers.onrender.com`
   (l'adresse exacte est affichée par Render). Les QR codes des reçus pointent vers cette adresse.

**À savoir (offre gratuite)** : le service s'endort après 15 minutes sans visite (premier accès
≈ 1 minute) ; le disque du conteneur est éphémère (sessions, journaux et PDF sont recréés au besoin,
les données restent dans la base MySQL). Les comptes de démonstration étant publics, mettez
`APP_DEMO` à `false` pour les masquer. Les rappels d'échéance peuvent être lancés par un service
*Cron Job* Render (offre payante) exécutant `php cron/rappels_echeances.php`.

---

## 5. Dépannage

| Symptôme | Solution |
|---|---|
| Page blanche ou erreur 500 | Consulter `storage/logs/app.log` (le détail s'affiche aussi en mode `development`). |
| « Can't connect to MySQL server » | Démarrer MySQL dans XAMPP et vérifier `DB_PORT` dans `config/config.php`. |
| Les URL renvoient 404 | Activer `mod_rewrite` et `AllowOverride All` ; vérifier `RewriteBase` dans `public/.htaccess`. |
| Reçu PDF en erreur (police, `file_get_contents`) | Projet dans OneDrive : le déplacer hors d'OneDrive ou « Toujours conserver sur cet appareil », puis `composer install`. |
| Aucun e-mail reçu | Normal avec `MAIL_DRIVER = 'log'` : voir `storage/logs/mail.log`. |
| Paiement en ligne resté « en attente » | Confirmer sur le simulateur ; sans réponse, il expire après 30 minutes et peut être relancé. |
