<?php
declare(strict_types=1);

namespace Cms\Front\Support;

use Cms\Admin\Domain\PostTypes\PostTypeRegistry;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Utils\LinkGenerator;
use Cms\Front\Data\PostProvider;
use DateTimeInterface;
use DOMDocument;

final class FeedBuilder
{
    private PostProvider $posts;
    private CmsSettings $settings;
    private LinkGenerator $links;
    private SimpleCache $cache;
    private int $defaultLimit;
    private int $defaultTtl;

    public function __construct(
        PostProvider $posts,
        ?CmsSettings $settings = null,
        ?LinkGenerator $links = null,
        ?SimpleCache $cache = null,
        int $defaultLimit = 20,
        int $defaultTtl = 300
    ) {
        $this->posts = $posts;
        $this->settings = $settings ?? new CmsSettings();
        $this->links = $links ?? new LinkGenerator();
        $this->cache = $cache ?? new SimpleCache();
        $this->defaultLimit = max(1, $defaultLimit);
        $this->defaultTtl = max(0, $defaultTtl);
    }

    public function build(array $options = []): string
    {
        $limit = isset($options['limit']) ? (int)$options['limit'] : $this->defaultLimit;
        $limit = max(1, $limit);

        $postTypes = $this->resolvePostTypes($options['post_types'] ?? null);
        $postTypes = Hooks::applyFilters('feed.post_types', $postTypes, $limit);
        if (!is_array($postTypes) || $postTypes === []) {
            return $this->buildXml([]);
        }
        $postTypes = array_values(array_unique(array_map('strval', $postTypes)));

        $ttl = $options['cache_ttl'] ?? $this->defaultTtl;
        $ttl = (int)Hooks::applyFilters('feed.cache_ttl', (int)$ttl, $postTypes, $limit);

        $signature = json_encode([
            'types' => $postTypes,
            'limit' => $limit,
        ]);
        $cacheKey = 'feed:' . md5($signature ?: microtime());

        $generator = function () use ($postTypes, $limit): string {
            $items = $this->posts->publishedAcrossTypes($postTypes, $limit, 0, true);
            $items = Hooks::applyFilters('feed.items', $items, $postTypes, $limit);
            if (!is_array($items)) {
                $items = [];
            }

            return $this->buildXml($items);
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
        $this->cache->clearNamespace('feed');
    }

    /**
     * @param array<int,string>|null $candidates
     * @return array<int,string>
     */
    private function resolvePostTypes(?array $candidates): array
    {
        $registered = PostTypeRegistry::all();
        $types = [];

        if ($candidates === null) {
            foreach ($registered as $type => $config) {
                if (!empty($config['public']) && !empty($config['feed'])) {
                    $types[$type] = $type;
                }
            }
            if ($types === []) {
                $types['post'] = 'post';
            }

            return array_values($types);
        }

        foreach ($candidates as $candidate) {
            $type = trim((string)$candidate);
            if ($type === '') {
                continue;
            }
            $config = $registered[$type] ?? null;
            if ($config !== null && !empty($config['public']) && !empty($config['feed'])) {
                $types[$type] = $type;
            }
        }

        if ($types === []) {
            foreach ($registered as $type => $config) {
                if (!empty($config['public']) && !empty($config['feed'])) {
                    $types[$type] = $type;
                }
            }
        }

        if ($types === []) {
            $types['post'] = 'post';
        }

        return array_values($types);
    }

    private function buildXml(array $items): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $rss = $doc->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $rss->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:content', 'http://purl.org/rss/1.0/modules/content/');
        $rss->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', 'http://www.w3.org/2005/Atom');
        $doc->appendChild($rss);

        $channel = $doc->createElement('channel');
        $rss->appendChild($channel);

        $siteTitle = $this->settings->siteTitle();
        $siteDescription = $this->settings->siteTagline();
        $siteUrl = $this->settings->siteUrl();
        $feedUrl = $this->links->absolute($this->links->feed());

        $channel->appendChild($doc->createElement('title', $siteTitle));
        $channel->appendChild($doc->createElement('link', $siteUrl));
        $channel->appendChild($doc->createElement('description', $siteDescription));
        $channel->appendChild($doc->createElement('language', $this->settings->siteLocale()));
        $channel->appendChild($doc->createElement('generator', 'CMS Frontend'));

        $atomLink = $doc->createElement('atom:link');
        $atomLink->setAttribute('href', $feedUrl);
        $atomLink->setAttribute('rel', 'self');
        $atomLink->setAttribute('type', 'application/rss+xml');
        $channel->appendChild($atomLink);

        $lastBuild = null;
        foreach ($items as $item) {
            $pubDate = $this->resolveDate((string)($item['published_at_raw'] ?? ''), (string)($item['created_at_raw'] ?? ''));
            if ($pubDate instanceof DateTimeInterface && ($lastBuild === null || $pubDate > $lastBuild)) {
                $lastBuild = $pubDate;
            }
            $channel->appendChild($this->buildItemNode($doc, $item));
        }

        $channel->appendChild($doc->createElement('lastBuildDate', ($lastBuild ?? DateTimeFactory::now())->format(DATE_RSS)));

        return (string)$doc->saveXML();
    }

    /**
     * @param array<string,mixed> $item
     */
    private function buildItemNode(DOMDocument $doc, array $item): \DOMElement
    {
        $node = $doc->createElement('item');

        $title = (string)($item['title'] ?? '');
        if ($title !== '') {
            $node->appendChild($doc->createElement('title', $title));
        }

        $link = $this->links->absolute((string)($item['permalink'] ?? ''));
        if ($link !== '') {
            $node->appendChild($doc->createElement('link', $link));
            $guid = $doc->createElement('guid', $link);
            $guid->setAttribute('isPermaLink', 'true');
            $node->appendChild($guid);
        }

        $pubDate = $this->resolveDate((string)($item['published_at_raw'] ?? ''), (string)($item['created_at_raw'] ?? ''));
        if ($pubDate instanceof DateTimeInterface) {
            $node->appendChild($doc->createElement('pubDate', $pubDate->format(DATE_RSS)));
        }

        $description = (string)($item['excerpt'] ?? '');
        if ($description !== '') {
            $descriptionNode = $doc->createElement('description');
            $descriptionNode->appendChild($doc->createTextNode($description));
            $node->appendChild($descriptionNode);
        }

        $content = (string)($item['content'] ?? '');
        if ($content !== '') {
            $contentNode = $doc->createElement('content:encoded');
            $contentNode->appendChild($doc->createCDATASection($content));
            $node->appendChild($contentNode);
        }

        return $node;
    }

    private function resolveDate(string ...$candidates): ?DateTimeInterface
    {
        $best = null;
        foreach ($candidates as $candidate) {
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

        return $best;
    }
}
