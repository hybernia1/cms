<?php
declare(strict_types=1);

namespace Cms\Front\Support;

use stdClass;

final class SimpleCache
{
    private string $directory;
    private bool $enabled;
    private int $defaultTtl;
    private object $miss;

    public function __construct(?string $directory = null, ?int $defaultTtl = 300, ?bool $enabled = null)
    {
        $baseDir = $directory ?? (defined('BASE_DIR') ? BASE_DIR . '/cache/front' : sys_get_temp_dir() . '/cms-cache');
        $this->directory = rtrim($baseDir, '/');
        $this->defaultTtl = max(0, (int)($defaultTtl ?? 300));
        $this->enabled = $enabled ?? ($this->defaultTtl > 0);
        $this->miss = new stdClass();

        if ($this->enabled) {
            $this->ensureDirectory($this->directory);
        }
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function defaultTtl(): int
    {
        return $this->defaultTtl;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->enabled) {
            return $default;
        }

        [$namespace, $path] = $this->pathForKey($key);
        if (!is_file($path)) {
            return $default;
        }

        $payload = @file_get_contents($path);
        if ($payload === false) {
            return $default;
        }

        $data = @unserialize($payload);
        if (!is_array($data) || !array_key_exists('value', $data)) {
            $this->safeUnlink($path);
            return $default;
        }

        $expires = $data['expires'] ?? null;
        if (is_int($expires) && $expires > 0 && $expires < time()) {
            $this->safeUnlink($path);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        if (!$this->enabled) {
            return;
        }

        [$namespace, $path] = $this->pathForKey($key);
        $this->ensureDirectory($namespace);

        $expires = null;
        $ttl = $ttl ?? $this->defaultTtl;
        if ($ttl !== null && $ttl > 0) {
            $expires = time() + (int)$ttl;
        }

        $payload = serialize([
            'expires' => $expires,
            'value' => $value,
        ]);

        if (@file_put_contents($path, $payload, LOCK_EX) === false) {
            $this->enabled = false;
            error_log(sprintf('SimpleCache: failed to write cache file "%s".', $path));
        }
    }

    public function remember(string $key, callable $producer, ?int $ttl = null): mixed
    {
        $miss = $this->miss;
        $cached = $this->get($key, $miss);
        if ($cached !== $miss) {
            return $cached;
        }

        $value = $producer();
        if ($this->enabled) {
            $this->set($key, $value, $ttl);
        }

        return $value;
    }

    public function delete(string $key): void
    {
        if (!$this->enabled) {
            return;
        }

        [, $path] = $this->pathForKey($key);
        $this->safeUnlink($path);
    }

    public function clear(): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->removeDirectory($this->directory);
        $this->ensureDirectory($this->directory);
    }

    public function clearNamespace(string $namespace): void
    {
        if (!$this->enabled) {
            return;
        }

        $target = $this->namespacePath($namespace);
        if (!is_dir($target)) {
            return;
        }

        $this->removeDirectory($target);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function pathForKey(string $key): array
    {
        $namespace = 'default';
        $name = $key;
        if (str_contains($key, ':')) {
            [$namespace, $name] = explode(':', $key, 2);
        }

        $namespacePath = $this->namespacePath($namespace);
        $filename = $this->filenameFor($name);

        return [$namespacePath, $namespacePath . '/' . $filename . '.cache'];
    }

    private function namespacePath(string $namespace): string
    {
        $sanitized = preg_replace('~[^a-zA-Z0-9_-]+~', '-', trim($namespace));
        if ($sanitized === '' || $sanitized === '-') {
            $sanitized = 'default';
        }

        return $this->directory . '/' . $sanitized;
    }

    private function filenameFor(string $name): string
    {
        $trimmed = trim($name);
        $hash = sha1($name);
        $sanitized = preg_replace('~[^a-zA-Z0-9._-]+~', '-', $trimmed);
        $sanitized = trim((string)$sanitized, '-_.');
        if ($sanitized === '') {
            return $hash;
        }

        return $sanitized . '-' . $hash;
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!@mkdir($path, 0775, true) && !is_dir($path)) {
            $this->enabled = false;
            error_log(sprintf('SimpleCache: unable to create cache directory "%s".', $path));
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        /** @var list<string> $files */
        $files = glob($path . '/*');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->removeDirectory($file);
                continue;
            }
            $this->safeUnlink($file);
        }

        @rmdir($path);
    }

    private function safeUnlink(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
