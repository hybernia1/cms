<?php
declare(strict_types=1);

namespace Cms\Http\Front;

final class FrontRouter
{
    /** @var array<int,array<string,mixed>> */
    private array $routes;

    /**
     * @param array<int,array<string,mixed>> $routes
     */
    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function routes(): array
    {
        return $this->routes;
    }

    /**
     * @param array<string,mixed> $query
     * @return array{handler: callable|null, params: array<string,string>}|null
     */
    public function matchByQuery(string $routeKey, array $query): ?array
    {
        foreach ($this->routes as $route) {
            if (($route['query'] ?? null) !== $routeKey) {
                continue;
            }

            $params = $this->extractQueryParams($route, $query);
            if (!$this->validateParams($route, $params)) {
                return ['handler' => null, 'params' => []];
            }

            return ['handler' => $route['handler'], 'params' => $params];
        }

        return null;
    }

    /**
     * @return array{handler: callable|null, params: array<string,string>}|null
     */
    public function matchByPath(string $path): ?array
    {
        $segments = $this->segments($path);

        foreach ($this->routes as $route) {
            if (!isset($route['path'])) {
                continue;
            }

            $pattern = $this->parsePathPattern((string)$route['path']);
            $params  = $this->matchPathPattern($pattern, $segments);
            if ($params === null) {
                continue;
            }

            if (!$this->validateParams($route, $params)) {
                return ['handler' => null, 'params' => []];
            }

            return ['handler' => $route['handler'], 'params' => $params];
        }

        return null;
    }

    /**
     * @param array<string,mixed> $route
     * @param array<string,mixed> $query
     * @return array<string,string>
     */
    private function extractQueryParams(array $route, array $query): array
    {
        $params = [];
        foreach ($route['queryParams'] ?? [] as $key => $definition) {
            if (is_int($key)) {
                $paramName = (string)$definition;
                $sourceKey = (string)$definition;
                $optional  = false;
            } elseif (is_array($definition)) {
                $paramName = (string)$key;
                $sourceKey = (string)($definition['key'] ?? $key);
                $optional  = (bool)($definition['optional'] ?? false);
            } else {
                $paramName = (string)$key;
                $sourceKey = (string)$definition;
                $optional  = false;
            }

            $value = isset($query[$sourceKey]) ? (string)$query[$sourceKey] : '';
            if ($value === '' && !$optional) {
                $params[$paramName] = '';
                continue;
            }

            $params[$paramName] = $value;
        }

        return $params;
    }

    /**
     * @param array<int,array<string,mixed>> $pattern
     * @param string[] $segments
     * @return array<string,string>|null
     */
    private function matchPathPattern(array $pattern, array $segments): ?array
    {
        if ($pattern === []) {
            return $segments === [] ? [] : null;
        }

        $required = 0;
        foreach ($pattern as $part) {
            if ($part['type'] === 'literal' || ($part['type'] === 'parameter' && !$part['optional'])) {
                $required++;
            }
        }

        $segmentCount = count($segments);
        if ($segmentCount < $required || $segmentCount > count($pattern)) {
            return null;
        }

        $params = [];
        foreach ($pattern as $index => $part) {
            $segment = $segments[$index] ?? null;

            if ($part['type'] === 'literal') {
                if ($segment !== $part['value']) {
                    return null;
                }
                continue;
            }

            if ($segment === null) {
                if ($part['optional']) {
                    $params[$part['name']] = '';
                    continue;
                }
                return null;
            }

            $params[$part['name']] = $segment;
        }

        foreach ($pattern as $part) {
            if ($part['type'] === 'parameter' && $part['optional'] && !array_key_exists($part['name'], $params)) {
                $params[$part['name']] = '';
            }
        }

        return $params;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function parsePathPattern(string $pattern): array
    {
        $trimmed = trim($pattern);
        if ($trimmed === '' || $trimmed === '/') {
            return [];
        }

        $segments = array_values(array_filter(explode('/', trim($trimmed, '/')), static fn($part) => $part !== ''));
        $result = [];

        foreach ($segments as $segment) {
            if (preg_match('/^\{([a-zA-Z0-9_]+)(\?)?\}$/', $segment, $matches)) {
                $result[] = [
                    'type'     => 'parameter',
                    'name'     => $matches[1],
                    'optional' => ($matches[2] ?? '') === '?',
                ];
                continue;
            }

            $result[] = [
                'type'  => 'literal',
                'value' => $segment,
            ];
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $route
     * @param array<string,string> $params
     */
    private function validateParams(array $route, array $params): bool
    {
        if (!isset($route['requirements'])) {
            return true;
        }

        foreach ($route['requirements'] as $key => $rule) {
            $value = $params[$key] ?? '';
            if ($value === '') {
                return false;
            }

            if (is_callable($rule)) {
                if (!$rule($value)) {
                    return false;
                }
                continue;
            }

            if (!preg_match('#^' . $rule . '$#u', $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string[]
     */
    private function segments(string $path): array
    {
        $trimmed = trim($path, '/');
        if ($trimmed === '') {
            return [];
        }

        return array_values(array_filter(explode('/', $trimmed), static fn($part) => $part !== ''));
    }
}
