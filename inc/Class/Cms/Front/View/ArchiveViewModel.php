<?php
declare(strict_types=1);

namespace Cms\Front\View;

final class ArchiveViewModel extends BaseFrontViewModel
{
    /** @param array<int,array<string,mixed>> $items */
    public function __construct(
        FrontViewContext $context,
        private readonly array $items,
        private readonly string $typeLabel,
        private readonly ?array $term,
        string $pageTitle
    ) {
        parent::__construct($context, $pageTitle);
    }

    protected function data(): array
    {
        return [
            'items' => $this->items,
            'type'  => $this->typeLabel,
            'term'  => $this->term,
        ];
    }
}
