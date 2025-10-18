<?php
declare(strict_types=1);

namespace Cms\Front\View;

final class SingleViewModel extends BaseFrontViewModel
{
    /**
     * @param array<string,mixed> $entity
     * @param array<int,array<string,mixed>> $commentsTree
     * @param array<string,array<int,array<string,mixed>>> $termsByType
     * @param array<string,string>|null $commentFlash
     */
    public function __construct(
        FrontViewContext $context,
        private readonly string $entityKey,
        private readonly array $entity,
        private readonly bool $commentsAllowed,
        private readonly array $commentsTree,
        private readonly string $csrfPublic,
        private readonly ?array $commentFlash,
        private readonly array $termsByType,
        string $pageTitle
    ) {
        parent::__construct($context, $pageTitle);
    }

    protected function data(): array
    {
        $payload = [
            'commentsTree'    => $this->commentsTree,
            'commentsAllowed' => $this->commentsAllowed,
            'csrfPublic'      => $this->csrfPublic,
            'commentFlash'    => $this->commentFlash,
            'termsByType'     => $this->termsByType,
        ];

        $payload[$this->entityKey] = $this->entity;

        return $payload;
    }
}
