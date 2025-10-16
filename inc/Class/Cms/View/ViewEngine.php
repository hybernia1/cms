<?php
declare(strict_types=1);

namespace Cms\View;

final class ViewEngine
{
    private string $basePath;
    /** @var array<string,mixed> */
    private array $shared = [];

    public function __construct(string $basePath)
    {
        $this->setBasePath($basePath);
    }

    public function setBasePath(string $basePath): void
    {
        $real = realpath($basePath);
        if ($real === false || !is_dir($real)) {
            throw new \RuntimeException("Template base path not found: {$basePath}");
        }
        $this->basePath = $real;
    }

    /**
     * Bezpečné sestavení absolutní cesty na základě $this->basePath.
     */
    private function resolve(string $template): string
    {
        $template = ltrim($template, '/');
        if (!str_ends_with($template, '.php')) {
            $template .= '.php';
        }
        $full = $this->basePath . '/' . $template;
        $real = realpath($full);
        if ($real === false || !str_starts_with($real, $this->basePath . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException("Template out of base path: {$template}");
        }
        return $real;
    }

    /**
     * Render šablony s daty. Pokud je $contentBlock, zavolej ho uvnitř šablony přes $content().
     * $data jsou extrahována do lokálního scope.
     */
    public function render(string $template, array $data = [], ?callable $contentBlock = null): void
    {
        $file = $this->resolve($template);
        $content = function() use ($contentBlock): void {
            if ($contentBlock) { $contentBlock(); }
        };
        $payload = $this->shared;
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $payload[$key] = $value;
            }
        }
        extract($payload, EXTR_OVERWRITE);
        include $file;
    }

    /**
     * Vloží partial (parts/*) s lokálními daty.
     */
    public function part(string $partial, array $data = []): void
    {
        $this->render($partial, $data);
    }

    public function share(array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $this->shared[$key] = $value;
            }
        }
    }
}
