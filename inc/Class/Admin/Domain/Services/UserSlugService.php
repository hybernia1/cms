<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Services;

use Cms\Admin\Domain\Repositories\UsersRepository;

final class UserSlugService
{
    public function __construct(private readonly UsersRepository $repository = new UsersRepository())
    {
    }

    public function generate(string $name, ?int $excludeId = null): string
    {
        $base = $this->slugify($name);
        if ($base === '') {
            $base = 'uzivatel';
        }

        $slug = $base;
        $suffix = 2;
        $normalizedExclude = $excludeId !== null && $excludeId > 0 ? $excludeId : null;

        while ($this->repository->slugExists($slug, $normalizedExclude)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugify(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            $normalized = mb_strtolower($normalized, 'UTF-8');
        } else {
            $normalized = strtolower($normalized);
        }

        $normalized = preg_replace('~[^\pL\d]+~u', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');

        if ($normalized === '') {
            return '';
        }

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($ascii) && $ascii !== '') {
            $normalized = strtolower($ascii);
        }

        $normalized = preg_replace('~[^a-z0-9-]+~', '', $normalized) ?? '';

        return trim($normalized, '-');
    }
}
