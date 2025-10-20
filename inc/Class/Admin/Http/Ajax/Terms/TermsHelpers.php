<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Terms;

trait TermsHelpers
{
    /**
     * @return array<string, array<string, string>>
     */
    protected function typeConfig(): array
    {
        return [
            'category' => [
                'nav'    => 'Kategorie',
                'list'   => 'Kategorie',
                'create' => 'Nová kategorie',
                'edit'   => 'Upravit kategorii',
                'label'  => 'Kategorie',
            ],
            'tag' => [
                'nav'    => 'Štítky',
                'list'   => 'Štítky',
                'create' => 'Nový štítek',
                'edit'   => 'Upravit štítek',
                'label'  => 'Štítek',
            ],
        ];
    }

    protected function normalizeType(null|string $type): string
    {
        $normalized = is_string($type) ? strtolower(trim($type)) : '';
        $config = $this->typeConfig();

        return array_key_exists($normalized, $config) ? $normalized : 'category';
    }

    protected function buildRedirectUrl(string $type, array $params = []): string
    {
        $query = array_merge([
            'r'    => 'terms',
            'type' => $this->normalizeType($type),
        ], $params);

        return 'admin.php?' . http_build_query($query);
    }
}
