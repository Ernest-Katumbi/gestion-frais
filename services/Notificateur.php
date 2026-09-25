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
            formaterMontant($p['montant']),
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
            . 'Montant payé   : ' . formaterMontant($p['montant']) . "\n"
            . 'Mode           : ' . Paiement::MODES[$p['mode']] . "\n"
            . "Référence      : {$p['reference']}\n"
            . 'Reçu           : ' . ($p['recu_numero'] ?? '—') . "\n"
            . 'Reste à payer  : ' . formaterMontant($reste) . "\n\n"
            . "Votre reçu est disponible dans votre espace parent : " . urlAbsolue('/parent') . "\n\n"
            . "Merci de votre confiance.\nLa comptabilité de l'" . APP_NOM;
        self::envoyerEmail($p['parent_email'], 'Paiement reçu — ' . $p['categorie'] . ' — ' . $eleve, $corps);
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
