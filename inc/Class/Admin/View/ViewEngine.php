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

    /**
     * Vloží partial s lokálními daty. Inspirace ve WordPress get_template_part().
     *
     * @param string               $slug   Základní název partialu (např. 'search').
     * @param string|array|null    $name   Volitelný variant (např. 'form') nebo přímo pole dat
     *                                     pro zpětnou kompatibilitu se starým API.
     * @param array<string,mixed>  $data   Data pro partial (pokud $name není pole).
     */
    public function part(string $slug, string|array|null $name = null, array $data = []): void
    {
        if (is_array($name) && $data === []) {
            $data = $name;
            $name = null;
        }

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Template part data must be an array.');
        }

        $variant = is_string($name) ? trim($name) : null;
        $candidates = $this->partCandidates($slug, $variant);

        $lastException = null;
        foreach ($candidates as $candidate) {
            try {
                $this->render($candidate, $data);
                return;
            } catch (\RuntimeException $e) {
                $lastException = $e;
            }
        }

        $message = sprintf(
            'Template part not found for slug "%s"%s. Looked into: %s',
            $slug,
            $variant ? ' and name "'.$variant.'"' : '',
            implode(', ', $candidates)
        );

        throw new \RuntimeException($message, 0, $lastException);
    }

    /**
     * @return string[]
     */
    private function partCandidates(string $slug, ?string $name): array
    {
        $normalized = trim(str_replace('\\', '/', $slug), '/');

        $candidates = [];
        if ($normalized !== '') {
            $candidates[] = $normalized;
            if (!str_starts_with($normalized, 'parts/')) {
                $candidates[] = 'parts/'.$normalized;
            }
        }

        $trimmed = $normalized;
        if (str_starts_with($trimmed, 'parts/')) {
            $trimmed = substr($trimmed, strlen('parts/')) ?: '';
        }

        if ($trimmed !== '' && $trimmed !== $normalized) {
            $candidates[] = $trimmed;
            if (!str_starts_with($trimmed, 'parts/')) {
                $candidates[] = 'parts/'.$trimmed;
            }
        }

        if ($trimmed !== '' && str_contains($trimmed, '/')) {
            $flattened = str_replace('/', '-', $trimmed);
            $candidates[] = $flattened;
            if (!str_starts_with($flattened, 'parts/')) {
                $candidates[] = 'parts/'.$flattened;
            }
        }

        $baseCandidates = array_values(array_unique(array_map(
            static function (string $candidate): string {
                $candidate = preg_replace('#/{2,}#', '/', $candidate) ?? $candidate;
                return ltrim($candidate, '/');
            },
            array_filter($candidates, static fn($value): bool => $value !== null && $value !== '')
        )));

        if ($name === null || $name === '') {
            return $baseCandidates;
        }

        $nameVariants = array_values(array_unique([
            $name,
            str_contains($name, '_') ? str_replace('_', '-', $name) : $name,
        ]));

        $withVariants = [];
        foreach ($nameVariants as $variant) {
            foreach ($baseCandidates as $candidate) {
                $withVariants[] = rtrim($candidate, '/').'-'.$variant;
                $withVariants[] = rtrim($candidate, '/').'/'.$variant;
            }

            if ($trimmed !== '') {
                $withVariants[] = $trimmed.'-'.$variant;
                $withVariants[] = $trimmed.'/'.$variant;
                if (str_contains($trimmed, '/')) {
                    $withVariants[] = str_replace('/', '-', $trimmed).'-'.$variant;
                }
            }
        }

        $all = array_merge($withVariants, $baseCandidates);

        return array_values(array_unique(array_map(
            static function (string $candidate): string {
                $candidate = preg_replace('#/{2,}#', '/', $candidate) ?? $candidate;
                return ltrim($candidate, '/');
            },
            array_filter($all, static fn($value): bool => $value !== null && $value !== '')
        )));
    }

    public function renderLayout(string $layout, string $template, array $data = []): void
    {
        $payload = $this->payload($data);
        $layoutResolved = $this->resolve($layout);
        $this->includeTemplate($layoutResolved, $payload, function() use ($template, $data): void {
            $this->render($template, $data);
        });
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
