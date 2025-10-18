<?php
declare(strict_types=1);

namespace Cms\Front\View;

final class NotFoundViewModel extends BaseFrontViewModel
{
    public function __construct(FrontViewContext $context)
    {
        parent::__construct($context, 'Stránka nenalezena');
    }

    protected function data(): array
    {
        return [];
    }
}
