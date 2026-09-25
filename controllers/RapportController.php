<?php
declare(strict_types=1);

/**
 * Pilotage financier du comptable : tableau de bord et rapports
 * (complété aux itérations 3 et 5).
 */
final class RapportController extends Controller
{
    public function tableauDeBord(): void
    {
        $utilisateur = exigerRole('comptable');
        $this->vue('comptable/tableau_de_bord', [
            'titre'       => 'Tableau de bord',
            'utilisateur' => $utilisateur,
        ]);
    }
}
