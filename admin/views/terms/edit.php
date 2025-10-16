<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array|null $term */
/** @var string $csrf */

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($term,$csrf) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $isEdit = (bool)$term;
  $actionUrl = $isEdit ? ('admin.php?r=terms&a=edit&id='.$h((string)$term['id'])) : 'admin.php?r=terms&a=create';
  $selected = fn(string $val, string $cur) => $val===$cur ? 'selected' : '';
?>
  <form class="card" method="post" action="<?= $actionUrl ?>">
    <div class="card-header"><?= $h($isEdit ? 'Upravit term #'.$term['id'] : 'Nový term') ?></div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">Název</label>
        <input class="form-control" name="name" required value="<?= $isEdit ? $h((string)$term['name']) : '' ?>">
      </div>

      <div class="mb-3">
        <label class="form-label">Slug</label>
        <input class="form-control" name="slug" value="<?= $isEdit ? $h((string)$term['slug']) : '' ?>">
        <div class="form-text">Nech prázdné pro automatické vygenerování ze jména.</div>
      </div>

      <div class="mb-3">
        <label class="form-label">Typ</label>
        <?php $curType = $isEdit ? (string)$term['type'] : 'category'; ?>
        <select class="form-select" name="type">
          <?php foreach (['category','tag'] as $t): ?>
            <option value="<?= $t ?>" <?= $selected($t, $curType) ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label">Popis</label>
        <textarea class="form-control" name="description" rows="4"><?= $isEdit ? $h((string)($term['description'] ?? '')) : '' ?></textarea>
      </div>

      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
    </div>
    <div class="card-footer d-flex gap-2">
      <button class="btn btn-primary" type="submit"><?= $h($isEdit ? 'Uložit změny' : 'Vytvořit') ?></button>
      <a class="btn btn-outline-secondary" href="admin.php?r=terms">Zpět na seznam</a>
    </div>
  </form>
<?php
});
