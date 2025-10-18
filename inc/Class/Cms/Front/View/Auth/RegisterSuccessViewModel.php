<?php
declare(strict_types=1);

namespace Cms\Front\View\Auth;

use Cms\Front\View\BaseFrontViewModel;
use Cms\Front\View\FrontViewContext;

final class RegisterSuccessViewModel extends BaseFrontViewModel
{
    public function __construct(
        FrontViewContext $context,
        private readonly string $email,
        private readonly bool $pendingApproval
    ) {
        parent::__construct($context, $pendingApproval ? 'Registrace odeslána' : 'Registrace dokončena');
    }

    protected function data(): array
    {
        return [
            'email'           => $this->email,
            'pendingApproval' => $this->pendingApproval,
        ];
    }
}
