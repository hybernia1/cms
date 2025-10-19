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
/** @var array<string,array<int,array<string,mixed>>> $quickAddOptions */
/** @var array<string,string> $linkTypeLabels */
/** @var array<string,string> $linkStatusMessages */

$this->render('layouts/base', compact('pageTitle', 'nav', 'currentUser', 'flash'), function () use ($csrf, $tablesReady, $menus, $menu, $menuId, $items, $editingItem, $parentOptions, $targets, $quickAddOptions) {
    $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $itemsById = [];
    foreach ($items as $it) {
        $itemsById[(int)$it['id']] = $it;
    }
    $targetLabels = [];
    foreach ($targets as $t) {
        $targetLabels[$t['value']] = $t['label'];
    }
    $targetIcons = [
        '_self' => ['icon' => 'bi-arrow-return-right', 'title' => 'Otevřít ve stejném okně'],
        '_blank' => ['icon' => 'bi-box-arrow-up-right', 'title' => 'Otevřít v novém okně'],
    ];
    $defaultOrder = $editingItem ? (int)($editingItem['sort_order'] ?? 0) : (count($items) + 1);
    $quickAddOptions = $quickAddOptions ?? ['pages' => [], 'posts' => [], 'categories' => [], 'system' => []];
    $hasQuickAdd = false;
    foreach ($quickAddOptions as $opts) {
        if (!empty($opts)) {
            $hasQuickAdd = true;
            break;
        }
    }
    $linkTypeLabels = $linkTypeLabels ?? [];
    $linkStatusMessages = $linkStatusMessages ?? [];
    $modalTabs = [
        'pages' => ['label' => 'Stránky', 'icon' => 'bi-file-earmark-text'],
        'posts' => ['label' => 'Příspěvky', 'icon' => 'bi-journal-text'],
        'categories' => ['label' => 'Kategorie', 'icon' => 'bi-folder'],
        'system' => ['label' => 'Systém', 'icon' => 'bi-gear'],
    ];
    $activeTab = 'pages';
    foreach (array_keys($modalTabs) as $key) {
        if (!empty($quickAddOptions[$key])) {
            $activeTab = $key;
            break;
        }
    }
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
            <form method="post" action="admin.php?r=navigation&a=update-menu" class="mb-3" data-ajax>
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
              <form method="post" action="admin.php?r=navigation&a=delete-menu" data-ajax data-confirm-modal="Opravdu smazat toto menu včetně všech položek?" data-confirm-modal-title="Smazat menu" data-confirm-modal-confirm-label="Ano, smazat" data-confirm-modal-cancel-label="Ne">
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
            <form method="post" action="admin.php?r=navigation&a=create-menu" data-ajax>
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
            <form method="post" id="navigation-item-form" action="admin.php?r=navigation&a=<?= $editingItem ? 'update-item' : 'create-item' ?>" data-ajax>
              <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
              <input type="hidden" name="menu_id" value="<?= $h((string)$menu['id']) ?>">
              <?php if ($editingItem): ?>
                <input type="hidden" name="id" value="<?= $h((string)$editingItem['id']) ?>">
              <?php endif; ?>
              <?php $currentLinkType = $editingItem ? (string)($editingItem['link_type'] ?? 'custom') : 'custom'; ?>
              <?php $currentLinkReference = $editingItem ? (string)($editingItem['link_reference'] ?? '') : ''; ?>
              <?php $currentLinkValid = $editingItem ? (bool)($editingItem['link_valid'] ?? true) : true; ?>
              <?php $currentLinkReason = $editingItem['link_reason'] ?? null; ?>
              <?php $currentLinkMeta = $editingItem['link_meta'] ?? []; ?>
              <input type="hidden" name="link_type" id="navigation-item-link-type" value="<?= $h($currentLinkType) ?>">
              <input type="hidden" name="link_reference" id="navigation-item-link-reference" value="<?= $h($currentLinkReference) ?>">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="navigation-item-title" class="form-label">Název</label>
                  <input type="text" id="navigation-item-title" name="title" class="form-control" value="<?= $editingItem ? $h((string)$editingItem['title']) : '' ?>" placeholder="např. O nás" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">URL</label>
                  <div class="input-group">
                    <input type="text" id="navigation-item-url" name="url" class="form-control" value="<?= $editingItem ? $h((string)$editingItem['url']) : '' ?>" placeholder="/cesta nebo https://...">
                    <?php if ($hasQuickAdd): ?>
                      <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#navigationContentModal" title="Vybrat existující obsah">
                        <i class="bi bi-collection" aria-hidden="true"></i>
                        <span class="d-none d-sm-inline">Vybrat obsah</span>
                      </button>
                    <?php endif; ?>
                  </div>
                  <?php $typeLabel = $linkTypeLabels[$currentLinkType] ?? ucfirst($currentLinkType); ?>
                  <?php $clearLabel = $currentLinkType === 'custom' ? 'Přepnout na vlastní URL' : 'Zrušit napojení'; ?>
                  <?php $slugInfo = ''; ?>
                  <?php if ($currentLinkType !== 'custom'): ?>
                    <?php if (is_array($currentLinkMeta) && !empty($currentLinkMeta['slug'])): ?>
                      <?php $slugInfo = 'Slug: ' . (string)$currentLinkMeta['slug']; ?>
                    <?php elseif (is_array($currentLinkMeta) && !empty($currentLinkMeta['route'])): ?>
                      <?php $slugInfo = 'Klíč: ' . (string)$currentLinkMeta['route']; ?>
                    <?php elseif ($currentLinkReference !== ''): ?>
                      <?php $slugInfo = 'Reference: ' . $currentLinkReference; ?>
                    <?php endif; ?>
                  <?php endif; ?>
                  <?php $warning = (!$currentLinkValid && is_string($currentLinkReason)) ? ($linkStatusMessages[$currentLinkReason] ?? 'Odkaz má problém a může vést na neplatnou stránku.') : ''; ?>
                  <div class="form-text">
                    Zadejte vlastní URL nebo vyberte existující stránku, příspěvek, kategorii či systémový odkaz.
                    <span class="badge text-bg-info ms-1<?= $currentLinkType === 'custom' ? ' d-none' : '' ?>" data-nav-link-badge>
                      Dynamický: <span data-nav-link-type-label><?= $h($typeLabel) ?></span>
                    </span>
                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-nav-clear-source><?= $h($clearLabel) ?></button>
                  </div>
                  <div class="form-text text-secondary<?= $slugInfo !== '' ? '' : ' d-none' ?>" data-nav-link-meta><?= $slugInfo !== '' ? $h($slugInfo) : '' ?></div>
                  <div class="form-text text-danger<?= $warning !== '' ? '' : ' d-none' ?>" data-nav-link-warning><?= $warning !== '' ? $h($warning) : '' ?></div>
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
                  <div class="form-text">Volitelné – položku můžete vnořit pod jinou.</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Pořadí</label>
                  <input type="number" name="sort_order" class="form-control" value="<?= $h((string)$defaultOrder) ?>">
                  <div class="form-text">Nižší číslo zobrazí položku výše.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">CSS třída</label>
                  <input type="text" name="css_class" class="form-control" value="<?= $editingItem ? $h((string)($editingItem['css_class'] ?? '')) : '' ?>" placeholder="např. btn-primary">
                  <div class="form-text">Volitelný styl odkazu (pokročilé použití).</div>
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
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Položka</th>
                  <th>URL</th>
                  <th style="width:90px" class="text-center">Cíl</th>
                  <th style="width:110px">Pořadí</th>
                  <th style="width:160px" class="text-end"></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $it): ?>
                  <?php $isEditing = $editingItem && (int)$editingItem['id'] === (int)$it['id']; ?>
                  <?php $indent = max(0, (int)$it['depth']) * 16; ?>
                  <tr class="<?= $isEditing ? 'table-active' : '' ?>">
                    <td>
                      <div style="padding-left: <?= $indent ?>px">
                        <div class="fw-semibold"><?= $h((string)$it['title']) ?></div>
                        <?php $itemLinkType = (string)($it['link_type'] ?? 'custom'); ?>
                        <?php $itemLinkLabel = $linkTypeLabels[$itemLinkType] ?? ucfirst($itemLinkType); ?>
                        <?php $itemLinkMeta = $it['link_meta'] ?? []; ?>
                        <div class="small text-secondary">
                          <?= $h($itemLinkLabel) ?>
                          <?php if (is_array($itemLinkMeta) && !empty($itemLinkMeta['slug'])): ?>
                            · Slug: <?= $h((string)$itemLinkMeta['slug']) ?>
                          <?php elseif (is_array($itemLinkMeta) && !empty($itemLinkMeta['route'])): ?>
                            · Klíč: <?= $h((string)$itemLinkMeta['route']) ?>
                          <?php elseif (!empty($it['link_reference']) && $itemLinkType !== 'custom'): ?>
                            · <?= $h((string)$it['link_reference']) ?>
                          <?php endif; ?>
                        </div>
                        <?php if (!empty($it['link_reason'])): ?>
                          <?php $reasonKey = (string)$it['link_reason']; ?>
                          <?php $reasonText = $linkStatusMessages[$reasonKey] ?? 'Odkaz má problém a může vést na neplatnou stránku.'; ?>
                          <div class="small text-danger">
                            <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i><?= $h($reasonText) ?>
                          </div>
                        <?php endif; ?>
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
                    <td class="text-center">
                      <?php
                        $targetKey = (string)($it['target'] ?? '_self');
                        $targetInfo = $targetIcons[$targetKey] ?? ['icon' => 'bi-question-circle', 'title' => $targetLabels[$targetKey] ?? $targetKey];
                      ?>
                      <span class="admin-icon-indicator" data-bs-toggle="tooltip" data-bs-title="<?= $h($targetInfo['title']) ?>">
                        <i class="bi <?= $h($targetInfo['icon']) ?>" aria-hidden="true"></i>
                        <span class="visually-hidden"><?= $h($targetInfo['title']) ?></span>
                      </span>
                    </td>
                    <td><?= $h((string)$it['sort_order']) ?></td>
                    <td class="text-end">
                      <div class="d-inline-flex gap-2 align-items-center">
                        <a class="admin-icon-btn" href="admin.php?r=navigation&menu_id=<?= $h((string)$menu['id']) ?>&item_id=<?= $h((string)$it['id']) ?>#item-form" aria-label="Upravit položku" data-bs-toggle="tooltip" data-bs-title="Upravit položku">
                          <i class="bi bi-pencil" aria-hidden="true"></i>
                        </a>
                        <form method="post" action="admin.php?r=navigation&a=delete-item" class="d-inline" data-ajax data-confirm-modal="Opravdu odstranit tuto položku?" data-confirm-modal-title="Smazat položku" data-confirm-modal-confirm-label="Ano, smazat" data-confirm-modal-cancel-label="Ne">
                          <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                          <input type="hidden" name="menu_id" value="<?= $h((string)$menu['id']) ?>">
                          <input type="hidden" name="id" value="<?= $h((string)$it['id']) ?>">
                          <button class="admin-icon-btn" type="submit" aria-label="Smazat položku" data-bs-toggle="tooltip" data-bs-title="Smazat položku">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$items): ?>
                  <tr>
                    <td colspan="5" class="text-center text-secondary py-4">Toto menu zatím neobsahuje žádné položky.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php if ($hasQuickAdd): ?>
          <div class="modal fade" id="navigationContentModal" tabindex="-1" aria-labelledby="navigationContentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title" id="navigationContentModalLabel">Vybrat existující obsah</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
                </div>
                <div class="modal-body">
                  <p class="text-secondary small">Vyberte existující stránku, příspěvek, kategorii nebo systémový odkaz. Název i URL se před uložením automaticky vyplní do formuláře, hodnoty můžete dále upravit.</p>
                  <ul class="nav nav-tabs" id="navigationContentTabs" role="tablist">
                    <?php foreach ($modalTabs as $key => $tab): ?>
                      <?php $disabled = empty($quickAddOptions[$key]); ?>
                      <?php $isActive = !$disabled && $key === $activeTab; ?>
                      <li class="nav-item" role="presentation">
                        <button class="nav-link<?= $isActive ? ' active' : '' ?><?= $disabled ? ' disabled' : '' ?>" id="navigation-content-tab-<?= $h($key) ?>" data-bs-toggle="tab" data-bs-target="#navigation-content-pane-<?= $h($key) ?>" type="button" role="tab" aria-controls="navigation-content-pane-<?= $h($key) ?>" aria-selected="<?= $isActive ? 'true' : 'false' ?>" <?= $disabled ? ' tabindex="-1" aria-disabled="true"' : '' ?>>
                          <?php if (!empty($tab['icon'])): ?>
                            <i class="bi <?= $h($tab['icon']) ?> me-1" aria-hidden="true"></i>
                          <?php endif; ?>
                          <?= $h($tab['label']) ?>
                        </button>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                  <div class="tab-content pt-3">
                    <?php foreach ($modalTabs as $key => $tab): ?>
                      <?php $itemsList = $quickAddOptions[$key] ?? []; ?>
                      <?php $isActive = $key === $activeTab && !empty($itemsList); ?>
                      <div class="tab-pane fade<?= $isActive ? ' show active' : '' ?>" id="navigation-content-pane-<?= $h($key) ?>" role="tabpanel" aria-labelledby="navigation-content-tab-<?= $h($key) ?>">
                        <?php if ($itemsList): ?>
                          <div class="list-group list-group-flush">
                            <?php foreach ($itemsList as $item): ?>
                              <?php $slug = (string)($item['slug'] ?? ''); ?>
                              <?php $title = (string)($item['title'] ?? ''); ?>
                              <?php $url = (string)($item['url'] ?? ''); ?>
                              <?php $linkType = (string)($item['link_type'] ?? ($item['type'] ?? 'custom')); ?>
                              <?php $linkReference = (string)($item['link_reference'] ?? ($item['id'] ?? '')); ?>
                              <?php $typeLabel = $linkTypeLabels[$linkType] ?? ucfirst($linkType); ?>
                              <?php $description = (string)($item['description'] ?? ''); ?>
                              <?php $status = (string)($item['status'] ?? ''); ?>
                              <?php $metaText = ''; ?>
                              <?php if ($linkType === 'route'): ?>
                                <?php $metaText = 'Klíč: ' . $linkReference; ?>
                              <?php elseif ($slug !== ''): ?>
                                <?php $metaText = 'Slug: ' . $slug; ?>
                              <?php elseif ($description !== ''): ?>
                                <?php $metaText = $description; ?>
                              <?php endif; ?>
                              <div class="list-group-item">
                                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-2">
                                  <div class="flex-grow-1">
                                    <div class="fw-semibold"><?= $h($title) ?></div>
                                    <div class="text-secondary small">Typ: <?= $h($typeLabel) ?></div>
                                    <?php if ($slug !== ''): ?>
                                      <div class="text-secondary small">Slug: <?= $h($slug) ?></div>
                                    <?php endif; ?>
                                    <?php if ($description !== '' && $description !== $metaText): ?>
                                      <div class="text-secondary small"><?= $h($description) ?></div>
                                    <?php endif; ?>
                                    <div class="text-secondary small text-break">URL: <?= $h($url) ?></div>
                                    <?php if ($status !== '' && $status !== 'publish'): ?>
                                      <div class="text-danger small">Stav: <?= $h($status) ?></div>
                                    <?php endif; ?>
                                  </div>
                                  <div class="text-nowrap">
                                    <button type="button" class="btn btn-sm btn-primary" data-nav-fill data-nav-target="#navigation-item-form" data-nav-title="<?= $h($title) ?>" data-nav-url="<?= $h($url) ?>" data-nav-type="<?= $h($linkType) ?>" data-nav-reference="<?= $h($linkReference) ?>" data-nav-type-label="<?= $h($typeLabel) ?>" data-nav-meta="<?= $h($metaText) ?>">
                                      <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Použít v položce
                                    </button>
                                  </div>
                                </div>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        <?php else: ?>
                          <p class="text-secondary small mb-0">Pro tuto sekci nejsou dostupné žádné položky.</p>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zavřít</button>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
<?php
});
