<?php
declare(strict_types=1);

/**
 * Moteur de vues minimal : une vue PHP est rendue puis insérée dans un gabarit (layout).
 * Toute donnée affichée dans une vue doit passer par e() (échappement HTML).
 */
final class View
{
    public static function rendre(string $vue, array $donnees = [], ?string $layout = 'layouts/app'): string
    {
        $contenu = self::inclure($vue, $donnees);
        if ($layout === null) {
            return $contenu;
        }
        return self::inclure($layout, $donnees + ['contenu' => $contenu]);
    }

    /** Rend un fragment de views/partials/. */
    public static function partiel(string $nom, array $donnees = []): string
    {
        return self::inclure('partials/' . $nom, $donnees);
    }

    private static function inclure(string $vue, array $donnees): string
    {
        $fichier = RACINE . '/views/' . $vue . '.php';
        if (!is_file($fichier)) {
            throw new RuntimeException("Vue introuvable : $vue");
        }
        extract($donnees, EXTR_SKIP);
        ob_start();
        try {
            require $fichier;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
