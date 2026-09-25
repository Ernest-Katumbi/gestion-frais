<?php
declare(strict_types=1);

/**
 * Envoi des notifications aux utilisateurs : e-mails (journalisés dans
 * storage/logs/mail.log avec le pilote « log ») et notifications internes.
 */
final class Notificateur
{
    /** Envoie un e-mail (pilote « log » : écriture dans storage/logs/mail.log). */
    public static function envoyerEmail(string $destinataire, string $sujet, string $corps): void
    {
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
