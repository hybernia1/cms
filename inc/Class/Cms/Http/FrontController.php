<?php
declare(strict_types=1);

namespace Cms\Http;

use Cms\Http\Front\ErrorController;
use Cms\Http\Front\FrontRouter;
use Cms\Http\Front\FrontRoutes;
use Cms\Http\Front\FrontServiceContainer;

final class FrontController
{
    private FrontServiceContainer $services;
    private FrontRouter $router;
    private ErrorController $errors;

    public function __construct(?FrontServiceContainer $services = null, ?FrontRouter $router = null)
    {
        $this->services = $services ?? new FrontServiceContainer();
        $routes = (new FrontRoutes($this->services))->all();
        $this->router = $router ?? new FrontRouter($routes);
        $this->errors = new ErrorController($this->services);
    }

    public function handle(): void
    {
        $routeKey = (string)($_GET['r'] ?? '');
        $match = $routeKey !== ''
            ? $this->router->matchByQuery($routeKey, $_GET)
            : $this->router->matchByPath($this->currentPath());

        if ($match === null || !isset($match['handler']) || !is_callable($match['handler'])) {
            $this->errors->notFound();
            return;
        }

        $handler = $match['handler'];
        $params  = $match['params'] ?? [];
        $handler($params);
    }

    private function currentPath(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        if ($base && $base !== '/' && str_starts_with($path, $base)) {
            $trimmed = substr($path, strlen($base));
            $path = $trimmed !== false && $trimmed !== '' ? $trimmed : '/';
        }
        $normalized = '/' . ltrim($path, '/');
        return $normalized === '//' ? '/' : $normalized;
    }
}
