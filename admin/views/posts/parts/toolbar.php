<?php
declare(strict_types=1);
/**
 * @var array<string,mixed> $toolbar
 */

$toolbar = is_array($toolbar ?? null) ? $toolbar : [];
$tabs = isset($toolbar['tabs']) && is_array($toolbar['tabs']) ? $toolbar['tabs'] : [];
$search = isset($toolbar['search']) && is_array($toolbar['search']) ? $toolbar['search'] : null;
$button = isset($toolbar['button']) && is_array($toolbar['button']) ? $toolbar['button'] : null;
$containerClasses = (string)($toolbar['containerClasses'] ?? 'd-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2 mb-3');
$tabsClass = (string)($toolbar['tabsClass'] ?? 'order-2 order-md-1');
?>
<div data-admin-fragment="posts-toolbar">
  <?php $this->render('parts/listing/toolbar', [
    'tabs' => $tabs,
    'search' => $search,
    'button' => $button,
    'containerClasses' => $containerClasses,
    'tabsClass' => $tabsClass,
  ]); ?>
</div>
