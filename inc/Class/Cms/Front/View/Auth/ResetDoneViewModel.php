<?php
declare(strict_types=1);

namespace Cms\Front\View\Auth;

use Cms\Front\View\BaseFrontViewModel;
use Cms\Front\View\FrontViewContext;

final class ResetDoneViewModel extends BaseFrontViewModel
{
    public function __construct(FrontViewContext $context)
    {
        parent::__construct($context, 'Obnova hesla');
    }

    protected function data(): array
    {
        return [];
    }
}
