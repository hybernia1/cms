<?php
declare(strict_types=1);

namespace Cms\Admin\View;

final class ViewEngine
{
    /** @var string[] */
    private array $basePaths = [];
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
        $this->basePaths = [$real];
    }

    /**
     * Nastav více základních cest (např. pro děděné šablony).
     * @param array<int,string> $basePaths
     */
    public function setBasePaths(array $basePaths): void
    {
        $resolved = [];
        foreach ($basePaths as $path) {
            $real = realpath($path);
            if ($real !== false && is_dir($real)) {
                $resolved[] = $real;
            }
        }
        if ($resolved === []) {
            throw new \RuntimeException('Template base paths list cannot be empty.');
        }
        $this->basePaths = $resolved;
    }

    /**
     * Bezpečné sestavení absolutní cesty na základě dostupných basePaths.
     */
    private function resolve(string $template): array
    {
        $template = ltrim($template, '/');
        if (!str_ends_with($template, '.php')) {
            $template .= '.php';
        }
        foreach ($this->basePaths as $base) {
            $full = $base . '/' . $template;
            $real = realpath($full);
            if ($real !== false && str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
                return ['file' => $real, 'base' => $base];
            }
        }
        throw new \RuntimeException("Template not found in base paths: {$template}");
    }

    private function includeTemplate(array $resolved, array $payload, ?callable $contentBlock): void
    {
        $file = $resolved['file'];
        $content = function() use ($contentBlock): void {
            if ($contentBlock) { $contentBlock(); }
        };
        extract($payload, EXTR_OVERWRITE);
        include $file;
    }

    private function payload(array $data): array
    {
        $payload = $this->shared;
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $payload[$key] = $value;
            }
        }
        return $payload;
    }

    /**
     * Render šablony s daty. Pokud je $contentBlock, zavolej ho uvnitř šablony přes $content().
     * $data jsou extrahována do lokálního scope.
     */
    public function render(string $template, array $data = [], ?callable $contentBlock = null): void
    {
        $resolved = $this->resolve($template);
        $payload  = $this->payload($data);
        $this->includeTemplate($resolved, $payload, $contentBlock);
    }

    public function renderToString(string $template, array $data = [], ?callable $contentBlock = null): string
    {
        ob_start();
        try {
            $this->render($template, $data, $contentBlock);
        } finally {
            $buffer = ob_get_clean();
        }

        return $buffer === false ? '' : $buffer;
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
