<?php
declare(strict_types=1);

/**
 * @var array{q:string,author:int} $filters
 * @var array<int,array{id:int,name:string,email:?string}> $authors
 * @var callable $buildUrl
 * @var int $total
 */

$filters = array_merge(['q' => '', 'author' => 0], $filters ?? []);
$authors = is_array($authors ?? null) ? $authors : [];
$buildUrl = $buildUrl ?? static fn (array $override = []): string => '#';
$total = (int)($total ?? 0);

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="d-flex flex-wrap gap-3 align-items-end justify-content-between mb-3">
  <form
    class="row g-2 align-items-end"
    method="get"
    action="admin.php"
    data-newsletter-campaigns-filter-form
  >
    <input type="hidden" name="r" value="newsletter-campaigns">
    <div class="col-auto">
      <label class="form-label" for="newsletter-campaign-filter-q">Hledat</label>
      <input
        class="form-control form-control-sm"
        type="search"
        id="newsletter-campaign-filter-q"
        name="q"
        value="<?= $h((string)$filters['q']) ?>"
        placeholder="Předmět nebo obsah"
      >
    </div>
    <div class="col-auto">
      <label class="form-label" for="newsletter-campaign-filter-author">Autor</label>
      <select class="form-select form-select-sm" id="newsletter-campaign-filter-author" name="author">
        <option value="0">Všichni</option>
        <?php foreach ($authors as $author): ?>
          <option value="<?= (int)($author['id'] ?? 0) ?>"<?= (int)$filters['author'] === (int)($author['id'] ?? 0) ? ' selected' : '' ?>>
            <?= $h((string)($author['name'] ?? 'Neznámý')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto d-flex gap-2">
      <button class="btn btn-primary btn-sm" type="submit">Filtrovat</button>
      <a class="btn btn-outline-secondary btn-sm" href="<?= $h((string)$buildUrl(['q' => null, 'author' => null, 'page' => null])) ?>">Vymazat</a>
    </div>
  </form>
  <div class="text-secondary small ms-auto">
    Celkem: <?= $total ?>
  </div>
</div>
