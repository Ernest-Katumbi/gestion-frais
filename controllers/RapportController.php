<?php
declare(strict_types=1);

/**
 * Pilotage financier du comptable : tableau de bord (rapports complets à l'itération 5).
 */
final class RapportController extends Controller
{
    public function tableauDeBord(): void
    {
        $utilisateur = exigerRole('comptable');
        $indicateurs = Frais::indicateurs();
        $du = (float) $indicateurs['du'];

        $this->vue('comptable/tableau_de_bord', [
            'titre'       => 'Tableau de bord',
            'utilisateur' => $utilisateur,
            'indicateurs' => $indicateurs,
            'taux'        => $du > 0 ? (float) $indicateurs['paye'] / $du * 100 : 0.0,
            'duJour'      => Paiement::encaissementsDuJour(),
            'recents'     => Paiement::recents(6),
            'echeances'   => Frais::echeancesProches(14),
        ]);
    }
}
