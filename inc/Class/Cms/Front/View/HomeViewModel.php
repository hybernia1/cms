<?php
declare(strict_types=1);

namespace Cms\Front\View;

final class HomeViewModel extends BaseFrontViewModel
{
    /** @param array<int,array<string,mixed>> $latestPosts */
    public function __construct(FrontViewContext $context, private readonly array $latestPosts)
    {
        parent::__construct($context, 'Poslední příspěvky');
    }

    protected function data(): array
    {
        return [
            'latestPosts' => $this->latestPosts,
        ];
    }
}
