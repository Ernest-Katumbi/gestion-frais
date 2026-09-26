<?php
declare(strict_types=1);

/**
 * Pilotage financier du comptable : tableau de bord, rapports d'encaissement
 * par période (jour, mois, intervalle) et export PDF.
 * Seuls les paiements « reussi » sont comptés.
 */
final class RapportController extends Controller
{
    private const MOIS = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    public function tableauDeBord(): void
    {
        $utilisateur = exigerRole('comptable');
        $debut = date('Y-m-d', strtotime('-13 days'));
        $this->vue('comptable/tableau_de_bord', [
            'titre'       => 'Tableau de bord',
            'utilisateur' => $utilisateur,
            'kpi'         => self::indicateurs(date('Y-m-01'), date('Y-m-d')),
            'duJour'      => Paiement::encaissementsDuJour(),
            'serie'       => self::points(Paiement::serie($debut, date('Y-m-d'), 'jour'), 'jour'),
            'recents'     => Paiement::recents(6),
            'echeances'   => Frais::echeancesProches(14),
        ]);
    }

    /** Rapport d'encaissement sur une période. */
    public function index(): void
    {
        exigerRole('comptable');
        $periode = $this->periode();
        $this->vue('comptable/rapports', ['titre' => 'Rapports', 'fil' => [['Rapports', null]]] + $this->donnees($periode));
    }

    /** Export PDF du rapport (même période que l'écran). */
    public function pdf(): void
    {
        $comptable = exigerRole('comptable');
        $periode = $this->periode();
        $html = View::rendre('pdf/rapport', $this->donnees($periode) + [
            'comptable' => $comptable,
            'logo'      => 'data:image/png;base64,' . base64_encode((string) file_get_contents(RACINE . '/public/assets/img/logo-institut.png')),
        ], null);
        $pdf = GenerateurRecu::rendrePdf($html, 'A4');
        $nom = 'rapport-encaissements-' . $periode['du'] . ($periode['du'] !== $periode['au'] ? '-au-' . $periode['au'] : '') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($this->query('telecharger') === '1' ? 'attachment' : 'inline') . '; filename="' . $nom . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store');
        echo $pdf;
        exit;
    }

    // --- Calculs ------------------------------------------------------------------------

    /**
     * Période demandée : jour (?type=jour&date=), mois (?type=mois&mois=AAAA-MM)
     * ou intervalle (?type=intervalle&du=&au=). Par défaut : le mois en cours.
     * @return array{type:string, du:string, au:string, libelle:string, pas:string, date:string, mois:string}
     */
    private function periode(): array
    {
        $type = $this->query('type', 'mois');
        $date = dateValide($this->query('date')) ? $this->query('date') : date('Y-m-d');
        $mois = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->query('mois')) ? $this->query('mois') : date('Y-m');

        if ($type === 'jour') {
            $du = $au = $date;
            $libelle = 'Journée du ' . formaterDate($date);
        } elseif ($type === 'intervalle' && dateValide($this->query('du')) && dateValide($this->query('au'))) {
            [$du, $au] = [$this->query('du'), $this->query('au')];
            if ($du > $au) {
                [$du, $au] = [$au, $du];
            }
            $libelle = 'Du ' . formaterDate($du) . ' au ' . formaterDate($au);
        } else {
            $type = 'mois';
            $du = $mois . '-01';
            $au = date('Y-m-t', strtotime($du));
            $libelle = ucfirst(self::MOIS[(int) substr($mois, 5, 2)]) . ' ' . substr($mois, 0, 4);
        }
        // Graphique journalier jusqu'à 62 jours, mensuel au-delà.
        $jours = (int) (new DateTimeImmutable($du))->diff(new DateTimeImmutable($au))->days + 1;
        return ['type' => $type, 'du' => $du, 'au' => $au, 'libelle' => $libelle, 'pas' => $jours > 62 ? 'mois' : 'jour', 'date' => $date, 'mois' => $mois];
    }

    private function donnees(array $periode): array
    {
        return [
            'periode'       => $periode,
            'kpi'           => self::indicateurs($periode['du'], $periode['au']),
            'serie'         => self::points(Paiement::serie($periode['du'], $periode['au'], $periode['pas']), $periode['pas']),
            'parCategorie'  => Paiement::ventilation($periode['du'], $periode['au'], 'categorie'),
            'parClasse'     => Paiement::ventilation($periode['du'], $periode['au'], 'classe'),
            'parMode'       => Paiement::ventilation($periode['du'], $periode['au'], 'mode'),
            'impayesClasse' => Frais::impayesParClasse(),
        ];
    }

    /**
     * Indicateurs : encaissé sur la période, reste à recouvrer et taux de recouvrement
     * (situation à ce jour), part des paiements en ligne sur la période.
     */
    public static function indicateurs(string $du, string $au): array
    {
        $periode = Paiement::totauxPeriode($du, $au);
        $global = Frais::indicateurs();
        $somme = (float) $periode['somme'];
        return $periode + [
            'reste'      => $global['reste'],
            'du_total'   => $global['du'],
            'paye_total' => $global['paye'],
            'echus'      => (int) $global['echus'],
            'reste_echu' => $global['reste_echu'],
            'taux'       => (float) $global['du'] > 0 ? (float) $global['paye'] / (float) $global['du'] * 100 : 0.0,
            'part_ligne' => $somme > 0 ? (float) $periode['somme_en_ligne'] / $somme * 100 : 0.0,
        ];
    }

    /** Points du graphique : libellé court, libellé long (info-bulle), valeur. */
    private static function points(array $serie, string $pas): array
    {
        $jours = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
        return array_map(static function (array $p) use ($pas, $jours): array {
            $d = new DateTimeImmutable($pas === 'mois' ? $p['cle'] . '-01' : $p['cle']);
            return [
                'court'  => $pas === 'mois' ? mb_substr(self::MOIS[(int) $d->format('n')], 0, 4) . '. ' . $d->format('y') : $d->format('d/m'),
                'long'   => $pas === 'mois' ? ucfirst(self::MOIS[(int) $d->format('n')]) . ' ' . $d->format('Y') : $jours[(int) $d->format('w')] . ' ' . $d->format('d/m/Y'),
                'valeur' => $p['somme'],
                'nb'     => $p['nb'],
            ];
        }, $serie);
    }
}
