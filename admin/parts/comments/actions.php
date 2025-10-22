<?php
declare(strict_types=1);

/**
 * @var array<string,mixed> $comment
 * @var string $csrf
 * @var string|null $backUrl
 * @var string|null $statusBackUrl
 * @var string|null $deleteBackUrl
 * @var bool|null $showDetail
 * @var string|null $detailUrl
 * @var string|null $detailTooltip
 * @var string|null $detailAriaLabel
 * @var string|null $detailButtonClass
 * @var bool|null $showStatusActions
 * @var array<int,string>|null $statusActions
 * @var array<int,string>|null $statusActionOrder
 * @var array<string,array{route?:string,icon?:string,title?:string}>|null $statusActionDefinitions
 * @var string|null $statusButtonClass
 * @var bool|null $showDelete
 * @var string|null $deleteButtonClass
 * @var string|null $deleteButtonTooltip
 * @var array{message?:string,title?:string,confirm?:string,cancel?:string}|null $deleteConfirm
 * @var string|null $wrapperClass
 */

$commentData = is_array($comment ?? null) ? $comment : [];
$csrfToken = (string)($csrf ?? '');
$commentId = (int)($commentData['id'] ?? 0);
$commentStatus = (string)($commentData['status'] ?? '');

$back = (string)($backUrl ?? '');
if ($back === '') {
    $back = 'admin.php?r=comments';
}
$statusBack = (string)($statusBackUrl ?? $back);
$deleteBack = (string)($deleteBackUrl ?? $back);

$wrapperClassValue = trim((string)($wrapperClass ?? 'd-flex flex-wrap gap-1'));
$showDetailButton = ($showDetail ?? false) ? true : false;
$detailHref = (string)($detailUrl ?? ('admin.php?r=comments&a=show&id=' . $commentId));
$detailTooltipText = (string)($detailTooltip ?? 'Detail');
$detailAriaLabelText = (string)($detailAriaLabel ?? $detailTooltipText);
$detailButtonClassValue = trim((string)($detailButtonClass ?? 'btn btn-light btn-sm border px-2'));

$showStatus = ($showStatusActions ?? true) ? true : false;
$statusButtonClassValue = trim((string)($statusButtonClass ?? 'px-2'));
$statusActionsParam = isset($statusActions) ? $statusActions : null;
$statusActionOrderParam = isset($statusActionOrder) ? $statusActionOrder : null;
$statusActionDefinitionsParam = isset($statusActionDefinitions) ? $statusActionDefinitions : null;

$showDeleteButton = ($showDelete ?? true) ? true : false;
$deleteButtonClassValue = trim((string)($deleteButtonClass ?? 'px-2 text-danger'));
$deleteTooltipText = (string)($deleteButtonTooltip ?? 'Smazat');
$deleteConfirmParam = isset($deleteConfirm) ? $deleteConfirm : null;

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$defaultDefinitions = [
    'approve' => ['route' => 'approve', 'icon' => 'bi bi-check-lg', 'title' => 'Schválit komentář'],
    'draft'   => ['route' => 'draft',   'icon' => 'bi bi-file-earmark', 'title' => 'Uložit jako koncept'],
    'spam'    => ['route' => 'spam',    'icon' => 'bi bi-slash-circle', 'title' => 'Označit jako spam'],
];
if (is_array($statusActionDefinitionsParam)) {
    foreach ($statusActionDefinitionsParam as $key => $definition) {
        if (!is_string($key) || $key === '' || !is_array($definition)) {
            continue;
        }
        $current = $defaultDefinitions[$key] ?? ['route' => $key, 'icon' => '', 'title' => ucfirst($key)];
        if (array_key_exists('route', $definition) && $definition['route'] !== null) {
            $current['route'] = (string)$definition['route'];
        }
        if (array_key_exists('icon', $definition) && $definition['icon'] !== null) {
            $current['icon'] = (string)$definition['icon'];
        }
        if (array_key_exists('title', $definition) && $definition['title'] !== null) {
            $current['title'] = (string)$definition['title'];
        }
        $defaultDefinitions[$key] = $current;
    }
}

$defaultOrder = ['approve', 'draft', 'spam'];
$orderList = $defaultOrder;
if (is_array($statusActionOrderParam)) {
    $orderList = [];
    foreach ($statusActionOrderParam as $item) {
        if (is_string($item) || is_int($item) || is_float($item)) {
            $value = (string)$item;
            if ($value === '') {
                continue;
            }
            if (!in_array($value, $orderList, true)) {
                $orderList[] = $value;
            }
        }
    }
    if ($orderList === []) {
        $orderList = $defaultOrder;
    }
}

$defaultStatusActionMap = [
    'draft'     => ['approve', 'spam'],
    'published' => ['draft', 'spam'],
    'spam'      => ['approve', 'draft'],
];

$statusActionList = [];
if ($showStatus) {
    if (is_array($statusActionsParam)) {
        foreach ($statusActionsParam as $item) {
            if (is_string($item) || is_int($item) || is_float($item)) {
                $value = (string)$item;
                if ($value === '') {
                    continue;
                }
                if (!in_array($value, $statusActionList, true)) {
                    $statusActionList[] = $value;
                }
            }
        }
    } else {
        $statusActionList = $defaultStatusActionMap[$commentStatus] ?? [];
    }
}

$orderedStatusActions = [];
foreach ($orderList as $actionKey) {
    if (in_array($actionKey, $statusActionList, true)) {
        $orderedStatusActions[] = $actionKey;
    }
}
foreach ($statusActionList as $actionKey) {
    if (!in_array($actionKey, $orderedStatusActions, true)) {
        $orderedStatusActions[] = $actionKey;
    }
}

$defaultDeleteConfirm = [
    'message' => 'Opravdu smazat? Smaže i odpovědi.',
    'title'   => 'Potvrzení smazání',
    'confirm' => 'Smazat',
    'cancel'  => 'Zrušit',
];
$deleteConfirmData = $defaultDeleteConfirm;
if (is_array($deleteConfirmParam)) {
    foreach ($deleteConfirmParam as $key => $value) {
        if (!is_string($key) || !array_key_exists($key, $defaultDeleteConfirm)) {
            continue;
        }
        if ($value === null) {
            continue;
        }
        $deleteConfirmData[$key] = (string)$value;
    }
}
?>
<div class="<?= $h($wrapperClassValue) ?>">
  <?php if ($showDetailButton): ?>
    <a class="<?= $h($detailButtonClassValue) ?>"
       href="<?= $h($detailHref) ?>"
       aria-label="<?= $h($detailAriaLabelText) ?>"
       data-bs-toggle="tooltip"
       data-bs-title="<?= $h($detailTooltipText) ?>">
      <i class="bi bi-eye"></i>
    </a>
  <?php endif; ?>

  <?php if ($showStatus && $orderedStatusActions !== []): ?>
    <?php foreach ($orderedStatusActions as $actionKey):
        if (!isset($defaultDefinitions[$actionKey])) {
            continue;
        }
        $definition = $defaultDefinitions[$actionKey];
        $route = (string)($definition['route'] ?? $actionKey);
        $icon = (string)($definition['icon'] ?? '');
        $title = (string)($definition['title'] ?? ucfirst($actionKey));
        $this->render('parts/forms/confirm-action', [
            'action'         => 'admin.php?r=comments&a=' . $route,
            'csrf'           => $csrfToken,
            'hidden'         => [
                'id'    => $commentId,
                '_back' => $statusBack,
            ],
            'dataAttributes' => [
                'data-comments-action' => 'status',
                'data-comments-status' => $actionKey,
            ],
            'button'         => [
                'class'     => $statusButtonClassValue,
                'tooltip'   => $title,
                'ariaLabel' => $title,
                'icon'      => $icon,
            ],
        ]);
    endforeach; ?>
  <?php endif; ?>

  <?php if ($showDeleteButton): ?>
    <?php $this->render('parts/forms/confirm-action', [
        'action'         => 'admin.php?r=comments&a=delete',
        'csrf'           => $csrfToken,
        'hidden'         => [
            'id'    => $commentId,
            '_back' => $deleteBack,
        ],
        'dataAttributes' => [
            'data-comments-action' => 'delete',
        ],
        'button'         => [
            'class'     => $deleteButtonClassValue,
            'tooltip'   => $deleteTooltipText,
            'ariaLabel' => $deleteTooltipText,
            'icon'      => 'bi bi-trash',
        ],
        'confirm'        => $deleteConfirmData,
    ]); ?>
  <?php endif; ?>
</div>
