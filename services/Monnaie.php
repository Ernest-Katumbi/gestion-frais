<?php
declare(strict_types=1);

/**
 * Règles monétaires : conversion d'un versement dans la devise de base.
 *
 * Les frais, soldes et rapports sont toujours tenus dans la devise de base (DEVISE, le franc
 * par défaut). Un paiement peut être versé dans la devise étrangère (DEVISE_ETRANGERE, le
 * dollar) : il est alors converti au taux du jour, et le paiement conserve le montant versé,
 * la devise et le taux appliqué.
 */
final class Monnaie
{
    /** Devises acceptées pour un paiement : la devise de base, et l'étrangère si un taux existe. */
    public static function devisesAcceptees(?array $taux): array
    {
        return $taux !== null ? [DEVISE, DEVISE_ETRANGERE] : [DEVISE];
    }

    public static function arrondir(float $montant, ?string $devise = null): float
    {
        return round($montant, decimalesDevise($devise));
    }

    /**
     * Convertit un versement dans la devise de base.
     *
     * Tolérance d'arrondi : un versement en devise étrangère qui dépasse le reste à payer de
     * moins d'une plus petite unité de cette devise (un centime de dollar) solde exactement
     * le frais. Exemple à 2 850 FC : 52,64 USD = 150 024 FC pour un reste de 150 000 FC.
     *
     * @return array{montant_base: float, devise: string, montant_verse: float, taux: ?float, arrondi: bool}
     * @throws DomainException devise non acceptée, taux absent, montant nul ou négatif
     */
    public static function convertir(float $montantVerse, string $devise, ?float $taux, float $resteBase): array
    {
        $montantVerse = self::arrondir($montantVerse, $devise);
        if ($montantVerse <= 0) {
            throw new DomainException('Le montant versé doit être supérieur à zéro.');
        }
        if ($devise === DEVISE) {
            return ['montant_base' => $montantVerse, 'devise' => $devise, 'montant_verse' => $montantVerse, 'taux' => null, 'arrondi' => false];
        }
        if ($devise !== DEVISE_ETRANGERE) {
            throw new DomainException('Devise non acceptée : ' . $devise . '.');
        }
        if ($taux === null || $taux <= 0) {
            throw new DomainException('Aucun taux de change n\'est défini : le paiement en ' . symboleDevise($devise) . ' est indisponible.');
        }

        $base = self::arrondir($montantVerse * $taux);
        $arrondi = false;
        $uniteEtrangere = 10 ** -decimalesDevise($devise);        // 0,01 USD
        if ($base > $resteBase && $base - $resteBase <= $taux * $uniteEtrangere + 1e-9) {
            $base = self::arrondir($resteBase);
            $arrondi = true;
        }
        return ['montant_base' => $base, 'devise' => $devise, 'montant_verse' => $montantVerse, 'taux' => $taux, 'arrondi' => $arrondi];
    }

    /**
     * Montant à verser dans la devise étrangère pour couvrir un montant en devise de base
     * (arrondi au centime supérieur : avec la tolérance, il solde exactement le reste).
     */
    public static function versDevise(float $montantBase, float $taux, string $devise = DEVISE_ETRANGERE): float
    {
        $facteur = 10 ** decimalesDevise($devise);
        return ceil(round($montantBase / $taux * $facteur, 6)) / $facteur;
    }

    /** Libellé d'un versement : « 450 000 FC » ou « 150,00 USD (427 500 FC) ». */
    public static function libelleVersement(array $paiement): string
    {
        $devise = (string) ($paiement['devise_versee'] ?? DEVISE);
        if ($devise === DEVISE) {
            return formaterMontant($paiement['montant']);
        }
        return formaterMontant($paiement['montant_verse'], $devise) . ' (' . formaterMontant($paiement['montant']) . ')';
    }
}
