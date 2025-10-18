<?php
declare(strict_types=1);

namespace Cms\Front\View;

abstract class BaseFrontViewModel
{
    public function __construct(
        protected readonly FrontViewContext $context,
        private readonly ?string $pageTitle = null
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    final public function toArray(): array
    {
        $data = array_replace($this->context->shared(), $this->data());
        if ($this->pageTitle !== null && $this->pageTitle !== '') {
            $data['pageTitle'] = $this->pageTitle;
        }

        return $data;
    }

    /**
     * @return array<string,mixed>
     */
    abstract protected function data(): array;
}
