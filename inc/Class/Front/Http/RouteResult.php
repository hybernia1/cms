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
        public readonly ?string $layout = 'layout'
    ) {
    }
}
