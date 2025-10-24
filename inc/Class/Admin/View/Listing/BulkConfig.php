<?php
declare(strict_types=1);

namespace Cms\Admin\View\Listing;

/**
 * Value object encapsulating shared configuration for listing bulk actions.
 */
final class BulkConfig
{
    /**
     * @param array<string,string|int|null> $hidden
     */
    public function __construct(
        private readonly string $formId,
        private readonly string $action,
        private readonly string $csrf,
        private readonly string $selectAllId,
        private readonly string $rowSelector,
        private readonly string $actionSelectId,
        private readonly string $applyButtonId,
        private readonly string $counterId,
        private readonly array $hidden = [],
    ) {
    }

    public function formId(): string
    {
        return $this->formId;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function csrf(): string
    {
        return $this->csrf;
    }

    public function selectAllId(): string
    {
        return $this->selectAllId;
    }

    public function selectAllSelector(): string
    {
        return '#' . $this->selectAllId;
    }

    public function rowSelector(): string
    {
        return $this->rowSelector;
    }

    public function actionSelectId(): string
    {
        return $this->actionSelectId;
    }

    public function actionSelectSelector(): string
    {
        return '#' . $this->actionSelectId;
    }

    public function applyButtonId(): string
    {
        return $this->applyButtonId;
    }

    public function applyButtonSelector(): string
    {
        return '#' . $this->applyButtonId;
    }

    public function counterId(): string
    {
        return $this->counterId;
    }

    public function counterSelector(): string
    {
        return '#' . $this->counterId;
    }

    /**
     * @return array<string,string|int|null>
     */
    public function hidden(): array
    {
        return $this->hidden;
    }

    /**
     * Build parameters for the bulk form partial.
     *
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    public function formParams(array $overrides = []): array
    {
        $params = [
            'formId'       => $this->formId(),
            'action'       => $this->action(),
            'csrf'         => $this->csrf(),
            'selectAll'    => $this->selectAllSelector(),
            'rowSelector'  => $this->rowSelector(),
            'actionSelect' => $this->actionSelectSelector(),
            'applyButton'  => $this->applyButtonSelector(),
            'counter'      => $this->counterSelector(),
            'hidden'       => $this->hidden(),
        ];

        if (isset($overrides['hidden']) && is_array($overrides['hidden'])) {
            $params['hidden'] = array_replace($params['hidden'], $overrides['hidden']);
            unset($overrides['hidden']);
        }

        return array_replace($params, $overrides);
    }

    /**
     * Build parameters for the bulk header partial.
     *
     * @param array<int,array<string,mixed>> $options
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    public function headerParams(array $options, string $applyIcon = '', array $overrides = []): array
    {
        $params = [
            'formId'         => $this->formId(),
            'actionSelectId' => $this->actionSelectId(),
            'applyButtonId'  => $this->applyButtonId(),
            'options'        => $options,
            'counterId'      => $this->counterId(),
            'applyIcon'      => $applyIcon,
        ];

        return array_replace($params, $overrides);
    }
}
