<?php
declare(strict_types=1);

use Cms\Admin\Utils\LinkGenerator;
use Core\Database\Init as DB;
use Core\Widgets\WidgetRegistry;

cms_add_action('cms_register_widgets', static function (): void {
    WidgetRegistry::register('category-list', [
        'name'        => 'Seznam kategorií',
        'description' => 'Zobrazí seznam dostupných kategorií včetně počtu publikovaných příspěvků.',
        'areas'       => ['sidebar'],
        'render'      => static function (array $context = []): string {
            $linkGenerator = $context['links'] ?? null;
            if (!$linkGenerator instanceof LinkGenerator) {
                $linkGenerator = new LinkGenerator();
            }

            try {
                $rows = DB::query()
                    ->table('terms', 't')
                    ->select([
                        't.name',
                        't.slug',
                        "SUM(CASE WHEN p.status = 'publish' THEN 1 ELSE 0 END) AS published_count",
                    ])
                    ->leftJoin('post_terms pt', 'pt.term_id', '=', 't.id')
                    ->leftJoin('posts p', 'p.id', '=', 'pt.post_id')
                    ->where('t.type', '=', 'category')
                    ->groupBy(['t.id', 't.name', 't.slug'])
                    ->orderBy('t.name', 'ASC')
                    ->get() ?? [];
            } catch (\Throwable $exception) {
                error_log('Category widget failed to load terms: ' . $exception->getMessage());
                $rows = [];
            }

            $items = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $name = trim((string)($row['name'] ?? ''));
                $slug = trim((string)($row['slug'] ?? ''));
                if ($name === '' || $slug === '') {
                    continue;
                }

                $count = isset($row['published_count']) ? (int)$row['published_count'] : 0;
                $items[] = [
                    'name'  => $name,
                    'slug'  => $slug,
                    'count' => $count,
                ];
            }

            if ($items === []) {
                return '<p class="widget__empty">Žádné kategorie zatím nejsou k dispozici.</p>';
            }

            $listItems = [];
            foreach ($items as $item) {
                $name = htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8');
                $slug = $item['slug'];
                $count = $item['count'];
                $url = htmlspecialchars($linkGenerator->category($slug), ENT_QUOTES, 'UTF-8');

                $itemHtml = '<li class="widget__list-item">';
                $itemHtml .= '<a class="widget__link" href="' . $url . '">' . $name . '</a>';
                if ($count > 0) {
                    $itemHtml .= '<span class="widget__count" aria-label="Počet příspěvků v kategorii">' . $count . '</span>';
                }
                $itemHtml .= '</li>';

                $listItems[] = $itemHtml;
            }

            return '<ul class="widget__list">' . implode('', $listItems) . '</ul>';
        },
    ]);
});
