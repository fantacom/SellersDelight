<?php
declare(strict_types=1);

final class Router
{
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->routes[] = ['GET', $pattern, $handler];
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->routes[] = ['POST', $pattern, $handler];
    }

    /** Muster wie /anbieter/{slug}; Platzhalter passen auf ein Pfadsegment. */
    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as [$m, $pattern, $handler]) {
            if ($m !== $method) {
                continue;
            }
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
            if (preg_match($regex, $path, $match)) {
                if ($method === 'POST') {
                    verify_csrf();
                }
                $params = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler(...array_values(array_map('rawurldecode', $params)));
                return;
            }
        }
        not_found();
    }
}

function not_found(): void
{
    render_error(404, 'Seite nicht gefunden', 'Die angeforderte Seite existiert nicht (mehr).');
}
