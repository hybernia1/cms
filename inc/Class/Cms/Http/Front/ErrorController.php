<?php
declare(strict_types=1);

namespace Cms\Http\Front;

final class ErrorController extends BaseFrontController
{
    public function notFound(): void
    {
        $this->renderNotFound();
    }
}
