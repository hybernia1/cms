<?php
declare(strict_types=1);

namespace Core\Navigation;

final class MenuLocations
{
    /**
     * @var array<string,array{label:string,description: ?string}>
     */
    private static array $locations = [];

    private static function humanize(string $value): string
    {
        $normalized = str_replace(['-', '_'], ' ', $value);
        $normalized = preg_replace('~\s+~u', ' ', $normalized ?? '') ?? '';
        $normalized = trim($normalized);
        if ($normalized === '') {
            return 'Menu';
        }
        return ucwords(mb_strtolower($normalized, 'UTF-8'));
    }

    private static function normalizeKey(string $location): string
    {
        $trimmed = trim($location);
        if ($trimmed === '') {
            return '';
        }
        $lower = mb_strtolower($trimmed, 'UTF-8');
        if (strlen($lower) > 64) {
            return substr($lower, 0, 64);
        }
        return $lower;
    }

    /**
     * @param string $location
     * @param string|array{label?:string,description?:string|null} $data
     */
    public static function register(string $location, string|array $data, ?string $description = null): void
    {
        $key = self::normalizeKey($location);
        if ($key === '') {
            return;
        }

        $label = '';
        $desc = null;

        if (is_array($data)) {
            $label = is_string($data['label'] ?? null) ? trim((string)$data['label']) : '';
            $desc = isset($data['description']) && is_string($data['description'])
                ? trim((string)$data['description'])
                : null;
        } else {
            $label = trim($data);
            $desc = $description !== null ? trim($description) : null;
        }

        if ($label === '') {
            $label = self::humanize($key);
        }

        self::$locations[$key] = [
            'label' => $label,
            'description' => $desc !== '' ? $desc : null,
        ];
    }

    /**
     * @param array<string,string|array{label?:string,description?:string|null}> $menus
     */
    public static function registerMany(array $menus): void
    {
        foreach ($menus as $location => $data) {
            self::register((string)$location, $data);
        }
    }

    public static function reset(): void
    {
        self::$locations = [];
    }

    /**
     * @return array<string,array{label:string,description:?string}>
     */
    public static function all(): array
    {
        return self::$locations;
    }

    public static function has(string $location): bool
    {
        $key = self::normalizeKey($location);
        return $key !== '' && isset(self::$locations[$key]);
    }
}
