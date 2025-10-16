<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array|null $term */
/** @var string $csrf */
/** @var string $type */
/** @var array $types */

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($term,$csrf,$type,$types) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $isEdit = (bool)$term;
  $typeCfg = $types[$type] ?? ['label' => strtoupper($type), 'create' => 'Nový term'];
  $actionParams = $isEdit
    ? ['r'=>'terms','a'=>'edit','id'=>(int)($term['id'] ?? 0),'type'=>$type]
    : ['r'=>'terms','a'=>'create','type'=>$type];
  $actionUrl = 'admin.php?'.http_build_query($actionParams);
  ?>
  <form class="card" method="post" action="<?= $actionUrl ?>">
    <div class="card-header"><?= $h($isEdit ? ($typeCfg['edit'] ?? ('Upravit '.$typeCfg['label'])).' #'.$term['id'] : ($typeCfg['create'] ?? 'Nový term')) ?></div>
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
        <div class="form-control-plaintext fw-semibold"><?= $h((string)($typeCfg['label'] ?? $type)) ?></div>
        <?php if ($isEdit): ?><div class="form-text">Typ nelze měnit.</div><?php endif; ?>
        <input type="hidden" name="type" value="<?= $h($type) ?>">
      </div>

      <div class="mb-3">
        <label class="form-label">Popis</label>
        <textarea class="form-control" name="description" rows="4"><?= $isEdit ? $h((string)($term['description'] ?? '')) : '' ?></textarea>
      </div>

      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
    </div>
    <div class="card-footer d-flex gap-2">
      <button class="btn btn-primary" type="submit"><?= $h($isEdit ? 'Uložit změny' : 'Vytvořit') ?></button>
      <a class="btn btn-outline-secondary" href="<?= $h('admin.php?'.http_build_query(['r'=>'terms','type'=>$type])) ?>">Zpět na seznam</a>
    </div>
  </form>
<?php
});
