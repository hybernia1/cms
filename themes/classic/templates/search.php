<?php
/** @var \Cms\View\Assets $assets */
/** @var string $siteTitle */
/** @var array<int,array{id:int,title:string,slug:string,created_at:string,type:string}> $items */
/** @var string $query */
$this->render('layouts/base', compact('assets','siteTitle'), function() use ($items, $query) {
?>
  <div class="card">
    <h2 style="margin-top:0">Hledání</h2>
    <form method="get" action="./search" class="row g-2 mb-3">
      <div class="col-md-9">
        <input type="text" class="form-control" name="s" placeholder="Hledat…" value="<?= htmlspecialchars($query ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-md-3">
        <button class="btn btn-primary w-100" type="submit">Hledat</button>
      </div>
    </form>

    <?php if (!$query): ?>
      <p class="meta">Zadej hledaný výraz.</p>
    <?php elseif (!$items): ?>
      <p class="meta">Nic jsme nenašli.</p>
    <?php else: ?>
      <ul>
        <?php foreach ($items as $p): ?>
          <li>
            <a href="./post/<?= htmlspecialchars((string)$p['slug'], ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars((string)$p['title'], ENT_QUOTES, 'UTF-8') ?>
            </a>
            <span class="meta"> (<?= htmlspecialchars((string)$p['type'], ENT_QUOTES, 'UTF-8') ?>)</span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
<?php }); ?>
