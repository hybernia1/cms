<?php
declare(strict_types=1);

namespace Cms\Front\View;

use Cms\Http\Front\FrontServiceContainer;

final class FrontViewContext
{
    public function __construct(private readonly FrontServiceContainer $services)
    {
    }

    public function services(): FrontServiceContainer
    {
        return $this->services;
    }

    /**
     * @return array<string,mixed>
     */
    public function shared(): array
    {
        return [
            'assets'     => $this->services->assets(),
            'siteTitle'  => $this->services->settings()->siteTitle(),
            'frontUser'  => $this->services->frontUser(),
            'navigation' => $this->services->navigation(),
            'urls'       => $this->services->urls(),
        ];
    }
}
