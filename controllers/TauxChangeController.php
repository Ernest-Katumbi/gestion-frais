<?php
declare(strict_types=1);

/**
 * Taux de change : le comptable saisit le taux du jour (francs pour un dollar) ;
 * l'administrateur le consulte. Chaque saisie est conservée dans l'historique.
 */
final class TauxChangeController extends Controller
{
    /** Garde-fou contre une faute de frappe : variation maximale sans confirmation explicite. */
    private const VARIATION_MAX = 0.20;

    public function index(): void
    {
        $utilisateur = exigerRole('comptable', 'admin');
        $this->vue('comptable/taux_change', [
            'titre'      => 'Taux de change',
            'fil'        => [['Taux de change', null]],
            'actuel'     => TauxChange::actuel(),
            'historique' => TauxChange::historique(30),
            'peutModifier' => $utilisateur['role'] === 'comptable',
            'confirmer'  => Session::lireFlash('confirmer_taux'),
        ]);
    }

    public function enregistrer(): void
    {
        $comptable = exigerRole('comptable');
        $saisie = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $this->post('taux'));
        $erreurs = [];
        if (!preg_match('/^\d+(\.\d{1,6})?$/', $saisie) || (float) $saisie <= 0 || (float) $saisie > 10000000) {
            $erreurs['taux'] = 'Taux invalide : indiquez le nombre de ' . symboleDevise() . ' pour 1 ' . symboleDevise(DEVISE_ETRANGERE) . ' (ex. 2850).';
        }
        if ($erreurs) {
            $this->retourAvecErreurs('/taux-change', $erreurs);
        }
        $taux = (float) $saisie;

        // Une variation de plus de 20 % par rapport au taux en vigueur doit être confirmée.
        $actuel = TauxChange::actuel();
        if ($actuel !== null && $this->post('confirme') !== '1') {
            $variation = abs($taux - (float) $actuel['taux']) / (float) $actuel['taux'];
            if ($variation > self::VARIATION_MAX) {
                Session::flash('old', ['taux' => $this->post('taux')]);
                Session::flash('confirmer_taux', ['taux' => $taux, 'variation' => $variation]);
                Session::message('avertissement', sprintf('Le nouveau taux diffère de %s %% du taux en vigueur : confirmez-le.', number_format($variation * 100, 0, ',', ' ')));
                $this->rediriger('/taux-change');
            }
        }

        TauxChange::enregistrer($taux, (int) $comptable['id_utilisateur']);
        journaliser('app', 'Taux de change enregistré par ' . $comptable['email'] . ' : ' . formaterTaux($taux));
        Session::message('succes', 'Nouveau taux en vigueur : ' . formaterTaux($taux) . '. Il s\'applique aux prochains paiements en ' . symboleDevise(DEVISE_ETRANGERE) . '.');
        $this->rediriger('/taux-change');
    }
}
