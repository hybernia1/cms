<?php
declare(strict_types=1);

namespace Cms\Front\View;

final class TermsViewModel extends BaseFrontViewModel
{
    /**
     * @param array<int,array<string,mixed>> $terms
     * @param array<int,string> $availableTypes
     */
    public function __construct(
        FrontViewContext $context,
        private readonly array $terms,
        private readonly ?string $activeType,
        private readonly array $availableTypes,
        string $pageTitle
    ) {
        parent::__construct($context, $pageTitle);
    }

    protected function data(): array
    {
        return [
            'terms'          => $this->terms,
            'activeType'     => $this->activeType,
            'availableTypes' => $this->availableTypes,
        ];
    }
}
