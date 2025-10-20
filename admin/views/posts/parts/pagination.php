<?php
declare(strict_types=1);
/**
 * @var array<string,mixed> $pagination
 * @var callable $buildUrl
 */

$pagination = is_array($pagination ?? null) ? $pagination : [];
$page = max(1, (int)($pagination['page'] ?? 1));
$pages = max(1, (int)($pagination['pages'] ?? 1));
$ariaLabel = (string)($pagination['ariaLabel'] ?? 'Stránkování');
$buildUrl = $buildUrl ?? static fn(array $params = []): string => '#';

ob_start();
$this->render('parts/listing/pagination', [
    'page' => $page,
    'pages' => $pages,
    'buildUrl' => $buildUrl,
    'ariaLabel' => $ariaLabel,
]);
$content = ob_get_clean();
?>
<div data-admin-fragment="posts-pagination">
  <?= $content ?: '' ?>
</div>
