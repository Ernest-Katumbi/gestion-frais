<?php
/**
 * Simulateur de passerelle de paiement (sandbox locale, sans Internet).
 *
 * Représente ce qui se passe chez le prestataire :
 *  - Mobile Money : le téléphone du parent affiche la demande de confirmation (code PIN) ;
 *  - Carte : page de paiement hébergée par la passerelle (les données de carte ne
 *    passent jamais par l'application de l'Institut et ne sont pas conservées).
 * Au clic, le simulateur envoie la notification signée à public/callback.php.
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/core/bootstrap.php';

// Le simulateur n'existe qu'en mode sandbox : avec une passerelle réelle, il est désactivé.
if (PAYMENT_DRIVER !== 'simulateur') {
    http_response_code(404);
    exit('Simulateur désactivé.');
}

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; form-action 'self'; frame-ancestors 'none'");
header('Cache-Control: no-store');

$reference = is_string($_GET['ref'] ?? null) ? $_GET['ref'] : '';
$transaction = SimulateurPasserelle::transaction($reference);
$resultat = null; // écran final après action

// Actions possibles : [statut envoyé à la passerelle, motif, libellé affiché]
$actions = [
    'confirmer'   => ['SUCCESS', '', 'Paiement confirmé'],
    'refuser'     => ['FAILED', 'Paiement refusé par le titulaire', 'Paiement refusé'],
    'insuffisant' => ['FAILED', 'Solde insuffisant', 'Solde insuffisant'],
];

if ($transaction !== null && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $jeton = (string) ($_POST['jeton'] ?? '');
    $erreur = null;

    if (!hash_equals($transaction['jeton'], $jeton) || !isset($actions[$action])) {
        $erreur = 'Requête invalide.';
    } elseif ($transaction['etat'] !== 'en_attente') {
        $erreur = 'Cette transaction a déjà été traitée.';
    } elseif ($action === 'confirmer' && $transaction['mode'] === 'mobile_money' && !preg_match('/^\d{4}$/', (string) ($_POST['pin'] ?? ''))) {
        $erreur = 'Saisissez un code PIN à 4 chiffres.';
    } elseif ($action === 'confirmer' && $transaction['mode'] === 'carte') {
        // Contrôles de forme uniquement : les données de carte ne sont ni enregistrées ni transmises.
        $numero = preg_replace('/\D/', '', (string) ($_POST['numero'] ?? ''));
        if (strlen($numero) < 13 || strlen($numero) > 19 || !preg_match('#^(0[1-9]|1[0-2])/\d{2}$#', (string) ($_POST['expiration'] ?? '')) || !preg_match('/^\d{3,4}$/', (string) ($_POST['cvv'] ?? ''))) {
            $erreur = 'Vérifiez le numéro de carte, la date d\'expiration (MM/AA) et le code de sécurité.';
        } elseif (str_ends_with($numero, '0002')) {
            $action = 'refuser'; // carte de test « refusée »
            $actions['refuser'][1] = 'Carte refusée par la banque émettrice';
        }
    }

    if ($erreur === null) {
        [$statut, $raison, $libelle] = $actions[$action];
        $transaction['etat'] = $statut === 'SUCCESS' ? 'confirmee' : 'refusee';
        $transaction['traitee_le'] = date('Y-m-d H:i:s');
        SimulateurPasserelle::enregistrer($transaction);

        // Notification serveur à serveur, signée, vers l'application de l'Institut.
        $reponse = SimulateurPasserelle::notifier($transaction['reference'], $statut, $raison);
        $transaction['callback'] = ['code' => $reponse['code'], 'reponse' => $reponse['corps']];
        SimulateurPasserelle::enregistrer($transaction);
        $resultat = ['succes' => $statut === 'SUCCESS', 'libelle' => $libelle, 'raison' => $raison, 'callback' => $reponse['code']];
    }
}

$montant = $transaction ? formaterMontant($transaction['montant']) : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Passerelle de paiement — Sandbox</title>
    <link rel="stylesheet" href="simulateur.css?v=<?= (int) filemtime(__DIR__ . '/simulateur.css') ?>">
</head>
<body class="<?= $transaction && $transaction['mode'] === 'carte' ? 'mode-carte' : 'mode-mobile' ?>">
<div class="bandeau-sandbox">SANDBOX · Simulateur local de passerelle de paiement — aucune transaction réelle</div>

<?php if ($transaction === null): ?>
    <main class="page-carte">
        <div class="carte-boite">
            <h1>Transaction introuvable</h1>
            <p>Le lien de paiement est invalide ou a expiré.</p>
        </div>
    </main>

<?php elseif ($transaction['mode'] === 'mobile_money'): ?>
    <main class="scene">
        <div class="telephone" role="region" aria-label="Téléphone simulé">
            <div class="telephone__encoche"></div>
            <div class="telephone__barre"><span><?= date('H:i') ?></span><span>▂▄▆ 4G</span></div>
            <div class="telephone__ecran">
                <?php if ($resultat !== null || $transaction['etat'] !== 'en_attente'): ?>
                    <?php $ok = $resultat['succes'] ?? $transaction['etat'] === 'confirmee'; ?>
                    <div class="ussd ussd--resultat <?= $ok ? 'is-ok' : 'is-ko' ?>">
                        <div class="ussd__icone"><?= $ok ? '✓' : '✕' ?></div>
                        <h1><?= e($resultat['libelle'] ?? ($ok ? 'Paiement confirmé' : 'Paiement refusé')) ?></h1>
                        <p><?= $ok
                            ? 'Vous avez payé ' . e($montant) . ' à ' . e($transaction['marchand']) . '.'
                            : e(($resultat['raison'] ?? '') !== '' ? $resultat['raison'] : 'La transaction a été annulée.') . ' Aucun montant n\'a été débité.' ?></p>
                        <p class="ussd__ref">Réf. <?= e($transaction['reference']) ?></p>
                        <p class="ussd__note">Vous pouvez revenir sur l'application de l'Institut : elle se met à jour automatiquement.</p>
                    </div>
                <?php else: ?>
                    <form method="post" class="ussd">
                        <div class="ussd__entete"><?= e($transaction['operateur'] ?? 'Mobile Money') ?></div>
                        <p class="ussd__message">
                            Confirmez le paiement de <strong><?= e($montant) ?></strong> à <strong><?= e($transaction['marchand']) ?></strong>.
                        </p>
                        <p class="ussd__detail">Réf. <?= e($transaction['reference']) ?><br>Compte : <?= e($transaction['client']['telephone']) ?></p>
                        <?php if (!empty($erreur)): ?><p class="ussd__erreur"><?= e($erreur) ?></p><?php endif; ?>
                        <label for="pin">Saisissez votre code PIN</label>
                        <input type="password" id="pin" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" autocomplete="off" autofocus placeholder="••••">
                        <p class="ussd__aide">Sandbox : n'importe quel code à 4 chiffres.</p>
                        <input type="hidden" name="jeton" value="<?= e($transaction['jeton']) ?>">
                        <button type="submit" name="action" value="confirmer" class="ussd__btn ussd__btn--ok">Confirmer</button>
                        <div class="ussd__rangee">
                            <button type="submit" name="action" value="refuser" class="ussd__btn" formnovalidate>Refuser</button>
                            <button type="submit" name="action" value="insuffisant" class="ussd__btn ussd__btn--test" formnovalidate>Solde insuffisant</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            <div class="telephone__barre-bas"></div>
        </div>
        <p class="scene__legende">Téléphone Mobile Money simulé. Le bouton « Solde insuffisant » simule un compte sans provision.</p>
    </main>

<?php else: ?>
    <main class="page-carte">
        <div class="carte-boite">
            <div class="carte-boite__entete">
                <span class="cadenas">🔒</span>
                <div>
                    <strong>Paiement sécurisé</strong>
                    <span>Passerelle de paiement · page hébergée</span>
                </div>
            </div>
            <div class="carte-boite__marchand">
                <span><?= e($transaction['marchand']) ?></span>
                <strong><?= e($montant) ?></strong>
                <small>Réf. <?= e($transaction['reference']) ?></small>
            </div>

            <?php if ($resultat !== null || $transaction['etat'] !== 'en_attente'): ?>
                <?php $ok = $resultat['succes'] ?? $transaction['etat'] === 'confirmee'; ?>
                <div class="carte-resultat <?= $ok ? 'is-ok' : 'is-ko' ?>">
                    <div class="carte-resultat__icone"><?= $ok ? '✓' : '✕' ?></div>
                    <h1><?= e($resultat['libelle'] ?? ($ok ? 'Paiement accepté' : 'Paiement refusé')) ?></h1>
                    <p><?= $ok ? 'Votre carte a été débitée de ' . e($montant) . '.' : e(($resultat['raison'] ?? '') !== '' ? $resultat['raison'] : 'Transaction annulée') . '. Aucun montant n\'a été débité.' ?></p>
                    <?php if (!empty($transaction['url_retour'])): ?>
                        <a class="bouton" href="<?= e($transaction['url_retour']) ?>">Retourner sur le site de l'<?= e($transaction['marchand']) ?></a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <form method="post" class="formulaire-carte" autocomplete="off">
                    <?php if (!empty($erreur)): ?><p class="erreur"><?= e($erreur) ?></p><?php endif; ?>
                    <label>Numéro de carte
                        <input name="numero" inputmode="numeric" maxlength="23" placeholder="4242 4242 4242 4242" required>
                    </label>
                    <label>Titulaire de la carte
                        <input name="titulaire" maxlength="60" placeholder="<?= e(mb_strtoupper($transaction['client']['nom'])) ?>">
                    </label>
                    <div class="rangee">
                        <label>Expiration
                            <input name="expiration" maxlength="5" placeholder="MM/AA" required>
                        </label>
                        <label>Code de sécurité
                            <input name="cvv" inputmode="numeric" maxlength="4" placeholder="123" required>
                        </label>
                    </div>
                    <input type="hidden" name="jeton" value="<?= e($transaction['jeton']) ?>">
                    <button type="submit" name="action" value="confirmer" class="bouton">Payer <?= e($montant) ?></button>
                    <div class="rangee rangee--boutons">
                        <button type="submit" name="action" value="refuser" class="bouton bouton--secondaire" formnovalidate>Annuler</button>
                        <button type="submit" name="action" value="insuffisant" class="bouton bouton--test" formnovalidate>Solde insuffisant</button>
                    </div>
                    <p class="aide">Sandbox : toute carte valide en forme est acceptée ; une carte finissant par <code>0002</code> est refusée. Les données saisies ne sont pas conservées.</p>
                </form>
            <?php endif; ?>
        </div>
    </main>
<?php endif; ?>
</body>
</html>
