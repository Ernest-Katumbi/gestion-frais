<?php
declare(strict_types=1);

/**
 * Espace parent : enfants, frais dus, paiements en ligne, reçus
 * (complété aux itérations 3 à 5).
 */
final class ParentController extends Controller
{
    public function accueil(): void
    {
        $utilisateur = exigerRole('parent');
        $this->vue('parent/accueil', [
            'titre'       => 'Accueil',
            'utilisateur' => $utilisateur,
        ]);
    }
}
