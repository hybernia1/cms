<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array<int,array<string,mixed>> $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var string $csrf */
/** @var bool $tablesReady */
/** @var array<int,array<string,mixed>> $menus */
/** @var array<string,mixed>|null $menu */
/** @var int $menuId */
/** @var array<int,array<string,mixed>> $items */
/** @var array<string,mixed>|null $editingItem */
/** @var array<int,array{value:int,label:string,disabled:bool}> $parentOptions */
/** @var array<int,array{value:string,label:string}> $targets */

$this->render('layouts/base', compact('pageTitle', 'nav', 'currentUser', 'flash'), function () use ($csrf, $tablesReady, $menus, $menu, $menuId, $items, $editingItem, $parentOptions, $targets) {
    $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $itemsById = [];
    foreach ($items as $it) {
        $itemsById[(int)$it['id']] = $it;
    }
    $targetLabels = [];
    foreach ($targets as $t) {
        $targetLabels[$t['value']] = $t['label'];
    }
    $defaultOrder = $editingItem ? (int)($editingItem['sort_order'] ?? 0) : (count($items) + 1);
?>
  <?php if (!$tablesReady): ?>
    <div class="alert alert-warning">
      Tabulky pro navigaci nebyly nalezeny. Ujistěte se, že proběhly instalace nebo migrace databáze.
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header">Dostupná menu</div>
        <div class="list-group list-group-flush">
          <?php foreach ($menus as $m): ?>
            <?php $active = (int)$menuId === (int)$m['id']; ?>
            <a class="list-group-item list-group-item-action <?= $active ? 'active' : '' ?>"
               href="admin.php?r=navigation&menu_id=<?= $h((string)$m['id']) ?>">
              <div class="fw-semibold"><?= $h((string)$m['name']) ?></div>
              <div class="small text-secondary">
                <?= $h((string)$m['slug']) ?> · <?= $h((string)$m['location']) ?>
              </div>
            </a>
          <?php endforeach; ?>
          <?php if (!$menus): ?>
            <div class="list-group-item text-secondary">Žádná menu zatím nejsou vytvořena.</div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($menu): ?>
        <div class="card mb-3">
          <div class="card-header">Upravit menu</div>
          <div class="card-body">
            <form method="post" action="admin.php?r=navigation&a=update-menu" class="mb-3">
              <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
              <input type="hidden" name="id" value="<?= $h((string)$menu['id']) ?>">
              <div class="mb-3">
                <label class="form-label">Název</label>
                <input type="text" name="name" class="form-control" value="<?= $h((string)$menu['name']) ?>" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Slug</label>
                <input type="text" name="slug" class="form-control" value="<?= $h((string)$menu['slug']) ?>" placeholder="např. hlavni-menu">
              </div>
              <div class="mb-3">
                <label class="form-label">Lokace</label>
                <input type="text" name="location" class="form-control" value="<?= $h((string)$menu['location']) ?>" placeholder="např. primary">
              </div>
              <div class="mb-3">
                <label class="form-label">Popis</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Volitelný popis menu"><?= $h((string)($menu['description'] ?? '')) ?></textarea>
              </div>
              <div class="d-grid gap-2">
                <button class="btn btn-primary" type="submit">Uložit změny</button>
              </div>
            </form>
            <form method="post" action="admin.php?r=navigation&a=delete-menu" onsubmit="return confirm('Opravdu smazat toto menu včetně všech položek?');">
              <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
              <input type="hidden" name="id" value="<?= $h((string)$menu['id']) ?>">
              <button class="btn btn-outline-danger w-100" type="submit">Smazat menu</button>
            </form>
          </div>
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header">Nové menu</div>
        <div class="card-body">
          <?php if (!$tablesReady): ?>
            <p class="text-secondary small mb-0">Nejprve dokončete migrace databáze, poté bude možné menu vytvořit.</p>
          <?php else: ?>
            <form method="post" action="admin.php?r=navigation&a=create-menu">
              <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
              <div class="mb-3">
                <label class="form-label">Název</label>
                <input type="text" name="name" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Slug</label>
                <input type="text" name="slug" class="form-control" placeholder="automaticky dle názvu">
              </div>
              <div class="mb-3">
                <label class="form-label">Lokace</label>
                <input type="text" name="location" class="form-control" value="primary">
              </div>
              <div class="mb-3">
                <label class="form-label">Popis</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Volitelný popis"></textarea>
              </div>
              <div class="d-grid">
                <button class="btn btn-success" type="submit">Vytvořit menu</button>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <?php if (!$menu): ?>
        <div class="card">
          <div class="card-body">
            <p class="mb-0 text-secondary">Vyberte existující menu nebo vytvořte nové, abyste mohli spravovat jeho položky.</p>
          </div>
        </div>
      <?php else: ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="h5 m-0">Položky menu „<?= $h((string)$menu['name']) ?>“</h2>
          <a class="btn btn-outline-secondary btn-sm" href="admin.php?r=navigation&menu_id=<?= $h((string)$menu['id']) ?>">Zrušit úpravy</a>
        </div>

        <div class="card mb-3" id="item-form">
          <div class="card-header"><?= $editingItem ? 'Upravit položku' : 'Přidat položku' ?></div>
          <div class="card-body">
            <form method="post" action="admin.php?r=navigation&a=<?= $editingItem ? 'update-item' : 'create-item' ?>">
              <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
              <input type="hidden" name="menu_id" value="<?= $h((string)$menu['id']) ?>">
              <?php if ($editingItem): ?>
                <input type="hidden" name="id" value="<?= $h((string)$editingItem['id']) ?>">
              <?php endif; ?>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Název</label>
                  <input type="text" name="title" class="form-control" value="<?= $editingItem ? $h((string)$editingItem['title']) : '' ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">URL</label>
                  <input type="text" name="url" class="form-control" value="<?= $editingItem ? $h((string)$editingItem['url']) : '' ?>" placeholder="/cesta nebo https://..." required>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Cíl odkazu</label>
                  <select name="target" class="form-select">
                    <?php $currentTarget = $editingItem ? (string)($editingItem['target'] ?? '_self') : '_self'; ?>
                    <?php foreach ($targets as $opt): ?>
                      <option value="<?= $h($opt['value']) ?>" <?= $currentTarget === $opt['value'] ? 'selected' : '' ?>><?= $h($opt['label']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Rodič</label>
                  <?php $currentParent = $editingItem ? (int)($editingItem['parent_id'] ?? 0) : 0; ?>
                  <select name="parent_id" class="form-select">
                    <?php foreach ($parentOptions as $opt): ?>
                      <option value="<?= $h((string)$opt['value']) ?>" <?= $opt['disabled'] ? 'disabled' : '' ?> <?= $currentParent === (int)$opt['value'] ? 'selected' : '' ?>><?= $h($opt['label']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Pořadí</label>
                  <input type="number" name="sort_order" class="form-control" value="<?= $h((string)$defaultOrder) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">CSS třída</label>
                  <input type="text" name="css_class" class="form-control" value="<?= $editingItem ? $h((string)($editingItem['css_class'] ?? '')) : '' ?>" placeholder="např. btn-primary">
                </div>
                <div class="col-12">
                  <div class="d-flex gap-2">
                    <button class="btn btn-<?= $editingItem ? 'primary' : 'success' ?>" type="submit"><?= $editingItem ? 'Uložit položku' : 'Přidat položku' ?></button>
                    <?php if ($editingItem): ?>
                      <a class="btn btn-outline-secondary" href="admin.php?r=navigation&menu_id=<?= $h((string)$menu['id']) ?>">Zrušit</a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th style="width:80px">ID</th>
                  <th>Položka</th>
                  <th>URL</th>
                  <th style="width:130px">Cíl</th>
                  <th style="width:110px">Pořadí</th>
                  <th style="width:180px"></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $it): ?>
                  <?php $isEditing = $editingItem && (int)$editingItem['id'] === (int)$it['id']; ?>
                  <?php $indent = max(0, (int)$it['depth']) * 16; ?>
                  <tr class="<?= $isEditing ? 'table-active' : '' ?>">
                    <td>#<?= $h((string)$it['id']) ?></td>
                    <td>
                      <div style="padding-left: <?= $indent ?>px">
                        <div class="fw-semibold"><?= $h((string)$it['title']) ?></div>
                        <?php if (!empty($it['css_class'])): ?>
                          <span class="badge text-bg-secondary mt-1"><?= $h((string)$it['css_class']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($it['parent_id'])): ?>
                          <?php $parentTitle = $itemsById[(int)$it['parent_id']]['title'] ?? '—'; ?>
                          <div class="small text-secondary">Rodič: <?= $h((string)$parentTitle) ?></div>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td>
                      <div class="text-truncate" style="max-width:240px;">
                        <?= $h((string)$it['url']) ?>
                      </div>
                    </td>
                    <td><?= $h($targetLabels[$it['target']] ?? (string)$it['target']) ?></td>
                    <td><?= $h((string)$it['sort_order']) ?></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary" href="admin.php?r=navigation&menu_id=<?= $h((string)$menu['id']) ?>&item_id=<?= $h((string)$it['id']) ?>#item-form">Upravit</a>
                      <form method="post" action="admin.php?r=navigation&a=delete-item" class="d-inline" onsubmit="return confirm('Opravdu odstranit tuto položku?');">
                        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                        <input type="hidden" name="menu_id" value="<?= $h((string)$menu['id']) ?>">
                        <input type="hidden" name="id" value="<?= $h((string)$it['id']) ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Smazat</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$items): ?>
                  <tr>
                    <td colspan="6" class="text-center text-secondary py-4">Toto menu zatím neobsahuje žádné položky.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php
});
