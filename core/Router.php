<?php
declare(strict_types=1);

/**
 * Routeur : associe une méthode HTTP et un chemin (ex. /utilisateurs/{id}/modifier)
 * à une action de contrôleur. Toute requête POST est soumise au contrôle CSRF.
 */
final class Router
{
    private static ?Router $instance = null;

    /** @var array<string, array<string, array{0: class-string, 1: string}>> */
    private array $routes = ['GET' => [], 'POST' => []];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function get(string $motif, array $action): void
    {
        $this->routes['GET'][$motif] = $action;
    }

    public function post(string $motif, array $action): void
    {
        $this->routes['POST'][$motif] = $action;
    }

    /** Indique si une page GET existe (sert à construire la navigation). */
    public function existe(string $chemin): bool
    {
        return $this->trouver('GET', $chemin) !== null;
    }

    public function dispatcher(string $methode, string $uri): void
    {
        $methode = $methode === 'HEAD' ? 'GET' : strtoupper($methode);
        $chemin = self::cheminDepuisUri($uri);

        $route = $this->trouver($methode, $chemin);
        if ($route === null) {
            throw new HttpException('Page introuvable.', 404);
        }
        if ($methode === 'POST' && !Csrf::verifier()) {
            throw new HttpException('Votre formulaire a expiré. Rechargez la page et réessayez.', 403);
        }

        [[$classe, $action], $parametres] = $route;
        (new $classe())->$action(...$parametres);
    }

    /** @return array{0: array, 1: list<int>}|null */
    private function trouver(string $methode, string $chemin): ?array
    {
        foreach ($this->routes[$methode] ?? [] as $motif => $action) {
            $regex = '#^' . preg_replace('#\{[a-z_]+\}#', '(\d+)', $motif) . '$#';
            if (preg_match($regex, $chemin, $m)) {
                return [$action, array_map('intval', array_slice($m, 1))];
            }
        }
        return null;
    }

    /** Transforme /gestion-frais/public/utilisateurs?page=2 en /utilisateurs. */
    public static function cheminDepuisUri(string $uri): string
    {
        $chemin = rawurldecode((string) parse_url($uri, PHP_URL_PATH));
        $base = cheminBase();
        if ($base !== '' && str_starts_with($chemin, $base)) {
            $chemin = substr($chemin, strlen($base));
        }
        if (str_starts_with($chemin, '/index.php')) {
            $chemin = substr($chemin, strlen('/index.php'));
        }
        $chemin = '/' . trim($chemin, '/');
        return $chemin;
    }
}
