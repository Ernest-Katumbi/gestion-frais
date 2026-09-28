<?php
declare(strict_types=1);

/**
 * Envoi des notifications aux utilisateurs : e-mails (journalisés dans
 * storage/logs/mail.log avec le pilote « log ») et notifications internes.
 */
final class Notificateur
{
    /**
     * Envoie un e-mail selon MAIL_DRIVER :
     * - « log » (par défaut) : écriture dans storage/logs/mail.log, sans réseau ;
     * - « smtp » : envoi réel par PHPMailer (composer require phpmailer/phpmailer), avec
     *   repli sur le journal en cas d'échec pour ne jamais bloquer un paiement.
     */
    public static function envoyerEmail(string $destinataire, string $sujet, string $corps): void
    {
        if (MAIL_DRIVER === 'smtp' && class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->Port = SMTP_PORT;
                $mail->SMTPAuth = SMTP_USER !== '';
                $mail->Username = SMTP_USER;
                $mail->Password = SMTP_PASS;
                $mail->CharSet = 'UTF-8';
                $mail->setFrom(MAIL_FROM, MAIL_FROM_NOM);
                $mail->addAddress($destinataire);
                $mail->Subject = $sujet;
                $mail->Body = $corps;
                $mail->send();
                journaliser('mail', "E-MAIL ENVOYÉ PAR SMTP à $destinataire : $sujet");
                return;
            } catch (Throwable $e) {
                journaliser('app', 'Échec SMTP (' . $e->getMessage() . ') : e-mail conservé dans mail.log.');
            }
        }
        journaliser('mail', sprintf(
            "E-MAIL ENVOYÉ\n  De      : %s <%s>\n  À       : %s\n  Sujet   : %s\n  Message :\n%s\n%s",
            MAIL_FROM_NOM,
            MAIL_FROM,
            $destinataire,
            $sujet,
            '    ' . str_replace("\n", "\n    ", trim($corps)),
            str_repeat('-', 70)
        ));
    }

    /**
     * Paiement confirmé : notification interne au parent (cloche) et e-mail.
     * Appelé dans la transaction qui valide le paiement (guichet ou callback de la passerelle).
     */
    public static function paiementConfirme(int $idPaiement): void
    {
        $p = Paiement::trouver($idPaiement);
        if ($p === null) {
            throw new DomainException('Paiement introuvable.');
        }
        $eleve = $p['eleve_prenom'] . ' ' . $p['eleve_nom'];
        $reste = (float) $p['frais_reste'];
        $message = sprintf(
            'Paiement de %s reçu pour « %s » de %s. Reçu n° %s.%s',
            Monnaie::libelleVersement($p),
            $p['categorie'],
            $eleve,
            $p['recu_numero'] ?? '—',
            $reste > 0 ? ' Reste à payer : ' . formaterMontant($reste) . '.' : ' Ce frais est soldé.'
        );
        Notification::creer((int) $p['id_parent'], 'paiement', $message);

        $corps = "Bonjour {$p['parent_nom']},\n\n"
            . "Nous confirmons la réception de votre paiement.\n\n"
            . "Élève          : $eleve ({$p['matricule']}, {$p['classe']})\n"
            . "Frais          : {$p['categorie']}\n"
            . 'Montant payé   : ' . Monnaie::libelleVersement($p) . "\n"
            . ($p['devise_versee'] !== DEVISE ? 'Taux appliqué  : ' . formaterTaux($p['taux_applique'], $p['devise_versee']) . "\n" : '')
            . 'Mode           : ' . Paiement::MODES[$p['mode']] . "\n"
            . "Référence      : {$p['reference']}\n"
            . 'Reçu           : ' . ($p['recu_numero'] ?? '—') . "\n"
            . 'Reste à payer  : ' . formaterMontant($reste) . "\n\n"
            . "Votre reçu est disponible dans votre espace parent : " . urlAbsolue('/parent') . "\n\n"
            . "Merci de votre confiance.\nLa comptabilité de l'" . APP_NOM;
        self::envoyerEmail($p['parent_email'], 'Paiement reçu — ' . $p['categorie'] . ' — ' . $eleve, $corps);
    }

    /** Paiement en ligne refusé par la passerelle : notification et e-mail au parent. */
    public static function paiementEchoue(int $idPaiement, string $raison = ''): void
    {
        $p = Paiement::trouver($idPaiement);
        if ($p === null) {
            throw new DomainException('Paiement introuvable.');
        }
        $eleve = $p['eleve_prenom'] . ' ' . $p['eleve_nom'];
        $raison = $raison !== '' ? $raison : 'refusé par la passerelle';
        Notification::creer((int) $p['id_parent'], 'paiement', sprintf(
            'Échec du paiement de %s pour « %s » de %s (réf. %s) : %s. Aucun montant n\'a été débité.',
            Monnaie::libelleVersement($p), $p['categorie'], $eleve, $p['reference'], $raison
        ));
        self::envoyerEmail($p['parent_email'], 'Paiement non abouti — ' . $p['categorie'] . ' — ' . $eleve,
            "Bonjour {$p['parent_nom']},\n\n"
            . 'Votre paiement de ' . Monnaie::libelleVersement($p) . " pour « {$p['categorie']} » de $eleve n'a pas abouti.\n"
            . "Motif : $raison.\nRéférence : {$p['reference']}\n\n"
            . "Aucun montant n'a été débité. Vous pouvez réessayer depuis votre espace parent : " . urlAbsolue('/parent') . "\n\n"
            . "La comptabilité de l'" . APP_NOM);
    }

    /**
     * Rappel d'échéance (cron) : notification interne et e-mail.
     * @param array $f frais détaillé (Frais::trouver) avec parent_email et parent_nom
     */
    public static function rappelEcheance(array $f, string $message): void
    {
        Notification::creer((int) $f['id_parent'], 'echeance', $message);
        self::envoyerEmail($f['parent_email'], 'Rappel d\'échéance — ' . $f['categorie'],
            "Bonjour {$f['parent_nom']},\n\n$message\n\n"
            . "Vous pouvez payer en ligne (Mobile Money ou carte) depuis votre espace parent : " . urlAbsolue('/parent')
            . "\nou au guichet de l'Institut.\n\nLa comptabilité de l'" . APP_NOM);
    }

    /** E-mail de bienvenue contenant le mot de passe provisoire d'un nouveau compte. */
    public static function compteCree(array $utilisateur, string $motDePasseProvisoire): void
    {
        $corps = "Bonjour {$utilisateur['nom']},\n\n"
            . "Un compte a été créé pour vous sur la plateforme de gestion des frais scolaires de l'" . APP_NOM . ".\n\n"
            . "Adresse de connexion : " . urlAbsolue('/connexion') . "\n"
            . "Identifiant : {$utilisateur['email']}\n"
            . "Mot de passe provisoire : $motDePasseProvisoire\n\n"
            . "Nous vous recommandons de le modifier dès votre première connexion.\n\n"
            . "La direction de l'" . APP_NOM;
        self::envoyerEmail($utilisateur['email'], 'Votre compte ' . APP_NOM, $corps);
    }
}
