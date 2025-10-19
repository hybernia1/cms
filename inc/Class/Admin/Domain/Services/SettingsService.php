<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Services;

use Cms\Admin\Domain\Repositories\SettingsRepository;
use Cms\Admin\Utils\DateTimeFactory;

final class SettingsService
{
    public function __construct(private readonly SettingsRepository $repo = new SettingsRepository()) {}

    public function get(): array
    {
        return $this->repo->get();
    }

    /**
     * Bez migrací rozšiřitelná nastavení: data mergujeme do JSONu.
     * @param array<string,mixed> $kv
     */
    public function updateBasics(string $siteTitle, string $siteEmail, array $kv = []): int
    {
        $current = $this->repo->get();
        $data = $current['data'] ? json_decode((string)$current['data'], true) : [];
        if (!is_array($data)) $data = [];

        foreach ($kv as $k => $v) { $data[$k] = $v; }

        return $this->repo->update([
            'site_title' => $siteTitle,
            'site_email' => $siteEmail,
            'data'       => json_encode($data, JSON_UNESCAPED_UNICODE),
            'updated_at' => DateTimeFactory::nowString(),
        ]);
    }
}
