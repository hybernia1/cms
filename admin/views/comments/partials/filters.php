<?php
declare(strict_types=1);
/** @var array{status:string,q:string,post:string} $filters */
/** @var callable $buildUrl */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$status = (string)($filters['status'] ?? '');
$q = (string)($filters['q'] ?? '');
$postFilter = (string)($filters['post'] ?? '');
?>
<div class="card mb-3" data-comments-filters>
  <div class="card-body">
    <form class="row gy-2 gx-2 align-items-end"
          method="get"
          action="admin.php"
          data-comments-form="filters"
          data-ajax>
      <input type="hidden" name="r" value="comments">
      <input type="hidden" name="status" value="<?= $h($status) ?>">
      <input type="hidden" name="q" value="<?= $h($q) ?>">
      <div class="col-md-6 col-lg-4">
        <label class="form-label" for="filter-post">Příspěvek</label>
        <div class="input-group input-group-sm">
          <span class="input-group-text">Post</span>
          <input class="form-control" id="filter-post" name="post" placeholder="slug nebo ID" value="<?= $h($postFilter) ?>">
          <button class="btn btn-primary" type="submit">Filtrovat</button>
          <a class="btn btn-outline-secondary <?= $postFilter === '' ? 'disabled' : '' ?>"
             href="<?= $h($buildUrl(['post' => null, 'page' => null])) ?>"
             aria-label="Zrušit filtr"
             data-bs-toggle="tooltip"
             data-bs-title="Zrušit filtr">
            <i class="bi bi-x-circle"></i>
          </a>
        </div>
      </div>
    </form>
  </div>
</div>
