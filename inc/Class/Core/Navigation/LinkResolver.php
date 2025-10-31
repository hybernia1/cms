<?php
declare(strict_types=1);

namespace Core\Navigation;

use Cms\Admin\Utils\LinkGenerator;

final class LinkResolver
{
    private LinkGenerator $links;

    public function __construct(?LinkGenerator $links = null)
    {
        $this->links = $links ?? new LinkGenerator();
    }

    /**
     * @param array<string,mixed> $item
     * @return array{url:string, valid:bool, type:string, reference:string, reason:?string, meta:array<string,mixed>}
     */
    public function resolve(array $item): array
    {
        $type = $this->normalizeType((string)($item['link_type'] ?? 'custom'));
        $reference = trim((string)($item['link_reference'] ?? ''));
        $fallback = (string)($item['url'] ?? '');

        return match ($type) {
            'post', 'page', 'category' => $this->resolveUnsupportedContent($type, $reference, $fallback),
            'route'                     => $this->resolveRoute($reference, $fallback),
            default                     => $this->resolveCustom($fallback),
        };
    }

    private function normalizeType(string $type): string
    {
        $allowed = ['custom', 'post', 'page', 'category', 'route'];
        return in_array($type, $allowed, true) ? $type : 'custom';
    }

    /**
     * @return array{url:string, valid:bool, type:string, reference:string, reason:?string, meta:array<string,mixed>}
     */
    private function resolveCustom(string $url): array
    {
        $url = trim($url);
        $valid = $url !== '';
        return [
            'url' => $url,
            'valid' => $valid,
            'type' => 'custom',
            'reference' => '',
            'reason' => $valid ? null : 'custom-empty',
            'meta' => [],
        ];
    }

    /**
     * @return array{url:string, valid:bool, type:string, reference:string, reason:?string, meta:array<string,mixed>}
     */
    private function resolveUnsupportedContent(string $type, string $reference, string $fallback): array
    {
        return $this->invalid($type, $reference, $fallback, 'unsupported');
    }

    /**
     * @return array{url:string, valid:bool, type:string, reference:string, reason:?string, meta:array<string,mixed>}
     */
    private function resolveRoute(string $reference, string $fallback): array
    {
        $map = [
            'home' => fn(): string => $this->links->home(),
            'admin' => fn(): string => $this->links->admin(),
            'login' => fn(): string => $this->links->login(),
            'logout' => fn(): string => $this->links->logout(),
            'products' => fn(): string => $this->links->products(),
            'catalog' => fn(): string => $this->links->products(),
            'checkout' => fn(): string => $this->links->checkout(),
            'cart' => fn(): string => $this->links->checkout(),
        ];

        $key = strtolower(trim($reference));
        if (!isset($map[$key])) {
            return $this->invalid('route', $reference, $fallback, 'unknown-route');
        }

        $url = $map[$key]();

        return [
            'url' => $url,
            'valid' => true,
            'type' => 'route',
            'reference' => $key,
            'reason' => null,
            'meta' => [
                'route' => $key,
            ],
        ];
    }

    /**
     * @return array{url:string, valid:bool, type:string, reference:string, reason:?string, meta:array<string,mixed>}
     */
    private function invalid(string $type, string $reference, string $fallback, string $reason): array
    {
        return [
            'url' => trim($fallback),
            'valid' => false,
            'type' => $this->normalizeType($type),
            'reference' => trim((string)$reference),
            'reason' => $reason,
            'meta' => [],
        ];
    }
}
