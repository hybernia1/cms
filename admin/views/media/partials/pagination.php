<?php
declare(strict_types=1);
/** @var array{page:int,per_page:int,total:int,pages:int} $pagination */
/** @var callable $buildUrl */

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$page = max(1, (int)($pagination['page'] ?? 1));
$pages = max(1, (int)($pagination['pages'] ?? 1));

if ($pages <= 1) {
    return;
}
?>
<nav class="mt-3" aria-label="Stránkování">
  <ul class="pagination pagination-sm mb-0">
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= $h($buildUrl(['page' => max(1, $page - 1)])) ?>" data-media-page-link data-media-page="<?= $h((string)max(1, $page - 1)) ?>" aria-label="Předchozí">‹</a>
    </li>
    <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link" href="<?= $h($buildUrl(['page' => $i])) ?>" data-media-page-link data-media-page="<?= $h((string)$i) ?>"><?= $i ?></a>
      </li>
    <?php endfor; ?>
    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= $h($buildUrl(['page' => min($pages, $page + 1)])) ?>" data-media-page-link data-media-page="<?= $h((string)min($pages, $page + 1)) ?>" aria-label="Další">›</a>
    </li>
  </ul>
</nav>
