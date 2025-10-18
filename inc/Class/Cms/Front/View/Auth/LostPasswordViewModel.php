<?php
declare(strict_types=1);

namespace Cms\Front\View\Auth;

use Cms\Front\View\BaseFrontViewModel;
use Cms\Front\View\FrontViewContext;

final class LostPasswordViewModel extends BaseFrontViewModel
{
    public function __construct(
        FrontViewContext $context,
        private readonly string $csrf,
        private readonly ?string $messageType,
        private readonly ?string $message
    ) {
        parent::__construct($context, 'Zapomenuté heslo');
    }

    protected function data(): array
    {
        return [
            'csrfPublic' => $this->csrf,
            'type'       => $this->messageType,
            'msg'        => $this->message,
        ];
    }
}
