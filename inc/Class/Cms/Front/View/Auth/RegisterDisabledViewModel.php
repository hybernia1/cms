<?php
declare(strict_types=1);

namespace Cms\Front\View\Auth;

use Cms\Front\View\BaseFrontViewModel;
use Cms\Front\View\FrontViewContext;

final class RegisterDisabledViewModel extends BaseFrontViewModel
{
    public function __construct(FrontViewContext $context)
    {
        parent::__construct($context, 'Registrace');
    }

    protected function data(): array
    {
        return [];
    }
}
