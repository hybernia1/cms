<?php
declare(strict_types=1);

namespace Cms\Front\Support;

use Cms\Admin\Domain\PostTypes\PostTypeRegistry;
use Cms\Admin\Domain\Repositories\TermsRepository;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Utils\LinkGenerator;
use Cms\Front\Data\PostProvider;
use DateTimeInterface;
use DOMDocument;

final class SitemapBuilder
{
    private PostProvider $posts;
    private TermsRepository $terms;
    private CmsSettings $settings;
    private LinkGenerator $links;
    private SimpleCache $cache;
    private int $maxItems;
    private int $defaultTtl;

    public function __construct(
        PostProvider $posts,
        ?TermsRepository $terms = null,
        ?CmsSettings $settings = null,
        ?LinkGenerator $links = null,
        ?SimpleCache $cache = null,
        int $maxItems = 50000,
        int $defaultTtl = 600
    ) {
        $this->posts = $posts;
        $this->terms = $terms ?? new TermsRepository();
        $this->settings = $settings ?? new CmsSettings();
        $this->links = $links ?? new LinkGenerator();
        $this->cache = $cache ?? new SimpleCache();
        $this->maxItems = max(1, $maxItems);
        $this->defaultTtl = max(0, $defaultTtl);
    }

    public function build(array $options = []): string
    {
        $limit = isset($options['limit']) ? (int)$options['limit'] : $this->maxItems;
        $limit = max(1, min($this->maxItems, $limit));

        $postTypes = $this->resolvePostTypes($options['post_types'] ?? null);
        $postTypes = Hooks::applyFilters('sitemap.post_types', $postTypes);
        if (!is_array($postTypes) || $postTypes === []) {
            return $this->buildXml([]);
        }
        $postTypes = array_values(array_unique(array_map('strval', $postTypes)));

        $taxonomies = $this->resolveTaxonomies($options['taxonomies'] ?? null, $postTypes);
        $taxonomies = Hooks::applyFilters('sitemap.taxonomies', $taxonomies, $postTypes);
        if (!is_array($taxonomies)) {
            $taxonomies = [];
        }
        $taxonomies = array_values(array_unique(array_map('strval', $taxonomies)));

        $ttl = $options['cache_ttl'] ?? $this->defaultTtl;
        $ttl = (int)Hooks::applyFilters('sitemap.cache_ttl', (int)$ttl, $postTypes, $taxonomies);

        $signature = json_encode([
            'types' => $postTypes,
            'taxonomies' => $taxonomies,
            'limit' => $limit,
        ]);
        $cacheKey = 'sitemap:' . md5($signature ?: microtime());

        $generator = function () use ($postTypes, $taxonomies, $limit): string {
            $entries = $this->collectEntries($postTypes, $taxonomies, $limit);
            $entries = Hooks::applyFilters('sitemap.urls', $entries, $postTypes, $taxonomies);
            if (!is_array($entries)) {
                $entries = [];
            }

            return $this->buildXml($entries);
        };

        if ($ttl <= 0 || !$this->cache->enabled()) {
            return $generator();
        }

        /** @var string $content */
        $content = $this->cache->remember($cacheKey, $generator, $ttl);

        return $content;
    }

    public function invalidate(): void
    {
        $this->cache->clearNamespace('sitemap');
    }

    /**
     * @param array<int,string> $postTypes
     * @param array<int,string> $taxonomies
     * @return array<int,array<string,string|null>>
     */
    private function collectEntries(array $postTypes, array $taxonomies, int $limit): array
    {
        $entries = [];
        $posts = $this->posts->publishedAcrossTypes($postTypes, $limit, 0, false);
        foreach ($posts as $post) {
            $url = $this->links->absolute((string)($post['permalink'] ?? ''));
            if ($url === '') {
                continue;
            }
            $entry = [
                'loc' => $url,
                'lastmod' => $this->determineLastmod(
                    (string)($post['updated_at_raw'] ?? ''),
                    (string)($post['published_at_raw'] ?? ''),
                    (string)($post['created_at_raw'] ?? '')
                ),
                'changefreq' => null,
                'priority' => null,
                'type' => 'post',
            ];
            $entry = Hooks::applyFilters('sitemap.entry', $entry, 'post', $post);
            if (!is_array($entry) || !isset($entry['loc'])) {
                continue;
            }
            $entries[] = [
                'loc' => (string)$entry['loc'],
                'lastmod' => isset($entry['lastmod']) ? (string)$entry['lastmod'] : null,
                'changefreq' => isset($entry['changefreq']) ? (string)$entry['changefreq'] : null,
                'priority' => isset($entry['priority']) ? (string)$entry['priority'] : null,
            ];
            if (count($entries) >= $limit) {
                return $entries;
            }
        }

        if ($taxonomies === [] || count($entries) >= $limit) {
            return $entries;
        }

        $remaining = $limit - count($entries);
        $terms = $this->terms->forPublishedPosts($taxonomies, $postTypes, $remaining);
        foreach ($terms as $term) {
            $url = $this->links->absolute($this->linkForTerm($term));
            if ($url === '') {
                continue;
            }
            $entry = [
                'loc' => $url,
                'lastmod' => $this->determineLastmod((string)($term['last_related_at'] ?? '')),
                'changefreq' => null,
                'priority' => null,
                'type' => 'term',
            ];
            $entry = Hooks::applyFilters('sitemap.entry', $entry, 'term', $term);
            if (!is_array($entry) || !isset($entry['loc'])) {
                continue;
            }
            $entries[] = [
                'loc' => (string)$entry['loc'],
                'lastmod' => isset($entry['lastmod']) ? (string)$entry['lastmod'] : null,
                'changefreq' => isset($entry['changefreq']) ? (string)$entry['changefreq'] : null,
                'priority' => isset($entry['priority']) ? (string)$entry['priority'] : null,
            ];
            if (count($entries) >= $limit) {
                break;
            }
        }

        return $entries;
    }

    /**
     * @param array<int,string>|null $candidates
     * @return array<int,string>
     */
    private function resolvePostTypes(?array $candidates): array
    {
        $registered = PostTypeRegistry::all();
        $fallback = ['post', 'page'];
        $types = [];

        if ($candidates === null) {
            if ($registered !== []) {
                foreach ($registered as $type => $config) {
                    if (!empty($config['public']) && !empty($config['sitemap'])) {
                        $types[$type] = $type;
                    }
                }
            }

            if ($types === []) {
                foreach ($fallback as $type) {
                    $types[$type] = $type;
                }
            }

            return array_values($types);
        }

        foreach ($candidates as $candidate) {
            $type = trim((string)$candidate);
            if ($type === '') {
                continue;
            }

            $config = $registered[$type] ?? null;
            if ($config !== null) {
                if (!empty($config['public']) && !empty($config['sitemap'])) {
                    $types[$type] = $type;
                }
                continue;
            }

            if ($registered === [] && in_array($type, $fallback, true)) {
                $types[$type] = $type;
            }
        }

        if ($types === []) {
            if ($registered !== []) {
                foreach ($registered as $type => $config) {
                    if (!empty($config['public']) && !empty($config['sitemap'])) {
                        $types[$type] = $type;
                    }
                }
            } else {
                foreach ($fallback as $type) {
                    $types[$type] = $type;
                }
            }
        }

        if ($types === []) {
            $types['post'] = 'post';
        }

        return array_values($types);
    }

    /**
     * @param array<int,string>|null $taxonomies
     * @param array<int,string> $postTypes
     * @return array<int,string>
     */
    private function resolveTaxonomies(?array $taxonomies, array $postTypes): array
    {
        if ($taxonomies !== null) {
            $normalized = [];
            foreach ($taxonomies as $taxonomy) {
                $candidate = trim((string)$taxonomy);
                if ($candidate === '') {
                    continue;
                }
                $normalized[$candidate] = $candidate;
            }
            return array_values($normalized);
        }

        $collected = [];
        foreach ($postTypes as $type) {
            foreach (PostTypeRegistry::taxonomiesForType($type) as $taxonomy) {
                $collected[$taxonomy] = $taxonomy;
            }
        }

        if ($collected === []) {
            $collected = ['category' => 'category', 'tag' => 'tag'];
        }

        return array_values($collected);
    }

    private function buildXml(array $entries): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $urlset = $doc->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset');
        $doc->appendChild($urlset);

        foreach ($entries as $entry) {
            if (!is_array($entry) || empty($entry['loc'])) {
                continue;
            }
            $url = $doc->createElement('url');
            $loc = $doc->createElement('loc');
            $loc->appendChild($doc->createTextNode((string)$entry['loc']));
            $url->appendChild($loc);

            if (!empty($entry['lastmod'])) {
                $url->appendChild($doc->createElement('lastmod', (string)$entry['lastmod']));
            }
            if (!empty($entry['changefreq'])) {
                $url->appendChild($doc->createElement('changefreq', (string)$entry['changefreq']));
            }
            if (!empty($entry['priority'])) {
                $url->appendChild($doc->createElement('priority', (string)$entry['priority']));
            }

            $urlset->appendChild($url);
        }

        return (string)$doc->saveXML();
    }

    private function determineLastmod(string ...$dates): ?string
    {
        $best = null;
        foreach ($dates as $candidate) {
            $value = trim($candidate);
            if ($value === '') {
                continue;
            }
            $date = DateTimeFactory::fromStorage($value);
            if (!$date instanceof DateTimeInterface) {
                continue;
            }
            if ($best === null || $date > $best) {
                $best = $date;
            }
        }

        return $best?->format(DateTimeInterface::ATOM);
    }

    /**
     * @param array<string,mixed> $term
     */
    private function linkForTerm(array $term): string
    {
        $slug = trim((string)($term['slug'] ?? ''));
        if ($slug === '') {
            return '';
        }
        $type = (string)($term['type'] ?? '');

        if ($type === 'category') {
            return $this->links->category($slug);
        }
        if ($type === 'tag') {
            return $this->links->tag($slug);
        }

        return $this->links->term($slug, $type !== '' ? $type : null);
    }
}
