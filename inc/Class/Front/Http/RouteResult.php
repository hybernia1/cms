<?php
declare(strict_types=1);

namespace Cms\Front\Http;

final class RouteResult
{
    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        public readonly string $template,
        public readonly array $data = [],
        public readonly int $status = 200,
        public readonly ?string $layout = 'layout',
        public readonly ?string $contentType = null,
        public readonly ?string $rawBody = null,
        public readonly array $headers = []
    ) {
    }

    /**
     * @param array<string,string> $headers
     */
    public static function raw(string $body, string $contentType, int $status = 200, array $headers = []): self
    {
        return new self('', [], $status, layout: null, contentType: $contentType, rawBody: $body, headers: $headers);
    }
}
