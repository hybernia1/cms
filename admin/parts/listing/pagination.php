<?php
declare(strict_types=1);

/**
 * @var int $page
 * @var int $pages
 * @var callable $buildUrl
 * @var string|null $ariaLabel
 */

$page = max(1, (int)($page ?? 1));
$pages = max(1, (int)($pages ?? 1));
$ariaLabel = $ariaLabel ?? 'Stránkování';

if ($pages <= 1) {
    return;
}

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<nav class="mt-3" aria-label="<?= $h($ariaLabel) ?>">
  <ul class="pagination pagination-sm mb-0">
    <?php $prev = max(1, $page - 1); ?>
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= $h((string)$buildUrl(['page' => $prev])) ?>" aria-label="Předchozí">‹</a>
    </li>
    <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link" href="<?= $h((string)$buildUrl(['page' => $i])) ?>"><?= $i ?></a>
      </li>
    <?php endfor; ?>
    <?php $next = min($pages, $page + 1); ?>
    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= $h((string)$buildUrl(['page' => $next])) ?>" aria-label="Další">›</a>
    </li>
  </ul>
</nav>
