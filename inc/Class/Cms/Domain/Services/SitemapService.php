<?php
declare(strict_types=1);

namespace Cms\Domain\Services;

use Cms\Settings\CmsSettings;
use Cms\Utils\DateTimeFactory;
use Cms\Utils\LinkGenerator;
use Core\Database\Init as DB;
use DateTimeInterface;

final class SitemapService
{
    private const INDEX = ['filename' => 'sitemap.xml', 'route' => 'sitemap'];

    /** @var array<string,array{filename:string,route:string}> */
    private const SECTIONS = [
        'post'     => ['filename' => 'sitemap-post.xml', 'route' => 'sitemap-post'],
        'page'     => ['filename' => 'sitemap-page.xml', 'route' => 'sitemap-page'],
        'category' => ['filename' => 'sitemap-category.xml', 'route' => 'sitemap-category'],
        'tag'      => ['filename' => 'sitemap-tag.xml', 'route' => 'sitemap-tag'],
    ];

    private string $baseUrl;

    public function __construct(
        private readonly LinkGenerator $links,
        private readonly CmsSettings $settings = new CmsSettings()
    ) {
        $this->baseUrl = $this->resolveBaseUrl();
    }

    /**
     * @return array{filename:string,route:string}
     */
    public function indexMetadata(): array
    {
        return self::INDEX;
    }

    /**
     * @return array<string,array{filename:string,route:string}>
     */
    public function sections(): array
    {
        return self::SECTIONS;
    }

    public function renderIndex(): string
    {
        $entries = [];
        foreach ($this->sections() as $key => $meta) {
            $items = $this->sectionItems($key);
            $entries[] = [
                'loc'     => $this->sitemapLocation($meta),
                'lastmod' => $this->latestLastmod($items),
            ];
        }

        return $this->buildIndexXml($entries);
    }

    public function renderSection(string $section): ?string
    {
        if (!isset(self::SECTIONS[$section])) {
            return null;
        }

        $items = $this->sectionItems($section);
        return $this->buildSectionXml($items);
    }

    /**
     * @return array<int,array{loc:string,lastmod:?string}>
     */
    private function sectionItems(string $section): array
    {
        return match ($section) {
            'post', 'page' => $this->fetchPosts($section),
            'category', 'tag' => $this->fetchTerms($section),
            default => [],
        };
    }

    /**
     * @return array<int,array{loc:string,lastmod:?string}>
     */
    private function fetchPosts(string $type): array
    {
        $rows = DB::query()
            ->table('posts', 'p')
            ->select([
                'p.slug',
                'p.updated_at',
                'p.published_at',
                'p.created_at',
            ])
            ->where('p.type', '=', $type)
            ->where('p.status', '=', 'publish')
            ->orderBy('COALESCE(p.updated_at,p.published_at,p.created_at)', 'DESC')
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $slug = trim((string)($row['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $relative = $type === 'page'
                ? $this->links->page($slug)
                : $this->links->post($slug);

            $items[] = [
                'loc'     => $this->absolute($relative),
                'lastmod' => $this->extractLastmod($row),
            ];
        }

        return $items;
    }

    /**
     * @return array<int,array{loc:string,lastmod:?string}>
     */
    private function fetchTerms(string $type): array
    {
        $rows = DB::query()
            ->table('terms', 't')
            ->select([
                't.slug',
                "MAX(COALESCE(p.updated_at,p.published_at,p.created_at)) AS last_activity",
            ])
            ->join('post_terms pt', 'pt.term_id', '=', 't.id')
            ->join('posts p', 'pt.post_id', '=', 'p.id')
            ->where('t.type', '=', $type)
            ->where('p.status', '=', 'publish')
            ->groupBy(['t.id', 't.slug'])
            ->orderBy('last_activity', 'DESC')
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $slug = trim((string)($row['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $relative = $type === 'category'
                ? $this->links->category($slug)
                : $this->links->tag($slug);

            $items[] = [
                'loc'     => $this->absolute($relative),
                'lastmod' => $this->formatLastmod($row['last_activity'] ?? null),
            ];
        }

        return $items;
    }

    /**
     * @param array<int,array{loc:string,lastmod:?string}> $items
     */
    private function latestLastmod(array $items): ?string
    {
        $latest = null;
        foreach ($items as $item) {
            $lastmod = $item['lastmod'] ?? null;
            if ($lastmod === null) {
                continue;
            }

            if ($latest === null || strcmp($lastmod, $latest) > 0) {
                $latest = $lastmod;
            }
        }

        return $latest;
    }

    private function extractLastmod(array $row): ?string
    {
        $candidates = [
            $row['updated_at'] ?? null,
            $row['published_at'] ?? null,
            $row['created_at'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $formatted = $this->formatLastmod($candidate);
            if ($formatted !== null) {
                return $formatted;
            }
        }

        return null;
    }

    private function formatLastmod(null|string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $dt = DateTimeFactory::fromStorage($value);
        return $dt ? $dt->format(DateTimeInterface::ATOM) : null;
    }

    private function buildIndexXml(array $entries): string
    {
        $xml = $this->xmlDeclaration();
        $xml .= "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($entries as $entry) {
            if (($entry['loc'] ?? '') === '') {
                continue;
            }

            $xml .= "  <sitemap>\n";
            $xml .= '    <loc>' . $this->escapeXml($entry['loc']) . "</loc>\n";
            if (!empty($entry['lastmod'])) {
                $xml .= '    <lastmod>' . $this->escapeXml($entry['lastmod']) . "</lastmod>\n";
            }
            $xml .= "  </sitemap>\n";
        }

        $xml .= "</sitemapindex>\n";
        return $xml;
    }

    /**
     * @param array<int,array{loc:string,lastmod:?string}> $items
     */
    private function buildSectionXml(array $items): string
    {
        $xml = $this->xmlDeclaration();
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($items as $item) {
            if (($item['loc'] ?? '') === '') {
                continue;
            }

            $xml .= "  <url>\n";
            $xml .= '    <loc>' . $this->escapeXml($item['loc']) . "</loc>\n";
            if (!empty($item['lastmod'])) {
                $xml .= '    <lastmod>' . $this->escapeXml($item['lastmod']) . "</lastmod>\n";
            }
            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>\n";
        return $xml;
    }

    /**
     * @param array{filename:string,route:string} $meta
     */
    private function sitemapLocation(array $meta): string
    {
        if ($this->links->prettyUrlsEnabled()) {
            $path = $meta['filename'];
        } else {
            $path = 'index.php?' . http_build_query(['r' => $meta['route']]);
        }

        return $this->absolute($path);
    }

    private function xmlDeclaration(): string
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function absolute(string $relative): string
    {
        $clean = trim($relative);
        if ($clean === '') {
            return $this->baseUrl . '/';
        }

        if (str_starts_with($clean, './')) {
            $clean = substr($clean, 2);
        }

        if ($clean === '') {
            return $this->baseUrl . '/';
        }

        if ($clean[0] !== '/') {
            $clean = '/' . ltrim($clean, '/');
        }

        return $this->baseUrl . $clean;
    }

    private function resolveBaseUrl(): string
    {
        $base = trim($this->settings->siteUrl());
        if ($base === '') {
            $base = 'http://localhost';
        }

        return rtrim($base, '/');
    }
}
