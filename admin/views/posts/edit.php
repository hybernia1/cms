<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array|null $post */
/** @var string $csrf */
/** @var array{category:array<int,array{id:int,name:string,slug:string,type:string}>,tag:array<int,array{id:int,name:string,slug:string,type:string}>} $terms */
/** @var array{category:array<int,int>,tag:array<int,int>} $selected */
/** @var string $type */
/** @var array $types */

$this->render('layouts/base', compact('pageTitle','nav','currentUser'), function () use ($flash,$post,$csrf,$terms,$selected,$type,$types) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $isEdit = (bool)$post;
  $typeCfg = $types[$type] ?? ['create'=>'Nový příspěvek','edit'=>'Upravit příspěvek','label'=>strtoupper($type)];
  $actionParams = $isEdit
    ? ['r'=>'posts','a'=>'edit','id'=>(int)($post['id'] ?? 0),'type'=>$type]
    : ['r'=>'posts','a'=>'create','type'=>$type];
  $actionUrl = 'admin.php?'.http_build_query($actionParams);
  $selectedOpt = function(array $arr, int $id): string { return in_array($id, $arr, true) ? 'selected' : ''; };
  $checked  = fn(bool $b) => $b ? 'checked' : '';
?>
  <?php if ($flash): ?>
    <div class="alert alert-<?= $h((string)$flash['type']) ?>"><?= $h((string)$flash['msg']) ?></div>
  <?php endif; ?>

  <form class="card" method="post" action="<?= $h($actionUrl) ?>" enctype="multipart/form-data">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><?= $h($isEdit ? ($typeCfg['edit'] ?? 'Upravit položku').' #'.($post['id'] ?? '') : ($typeCfg['create'] ?? 'Nová položka')) ?></span>
      <a class="btn btn-sm btn-outline-secondary" href="admin.php?r=terms">Správa termů</a>
    </div>

    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">Titulek</label>
        <input class="form-control" name="title" required value="<?= $isEdit ? $h((string)$post['title']) : '' ?>">
      </div>

      <?php if ($isEdit): ?>
        <div class="mb-3">
          <label class="form-label">Slug</label>
          <input class="form-control" name="slug" value="<?= $h((string)$post['slug']) ?>">
          <div class="form-text">Nech prázdné, pokud nechceš měnit.</div>
        </div>
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Typ</label>
          <div class="form-control-plaintext fw-semibold"><?= $h((string)($typeCfg['label'] ?? strtoupper($type))) ?></div>
          <?php if ($isEdit): ?><div class="form-text">Typ nelze měnit.</div><?php endif; ?>
        </div>
        <div class="col-md-4">
          <label class="form-label">Status</label>
          <?php $curStatus = $isEdit ? (string)$post['status'] : 'draft'; ?>
          <select class="form-select" name="status">
            <option value="draft"   <?= $curStatus==='draft'?'selected':'' ?>>draft</option>
            <option value="publish" <?= $curStatus==='publish'?'selected':'' ?>>publish</option>
          </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <?php $allow = $isEdit ? ((int)$post['comments_allowed']===1) : true; ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="comments" name="comments_allowed" <?= $checked($allow) ?>>
            <label class="form-check-label" for="comments">Povolit komentáře</label>
          </div>
        </div>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-md-6">
          <label class="form-label">Kategorie</label>
          <select class="form-select" name="categories[]" multiple size="6">
            <?php foreach (($terms['category'] ?? []) as $t): ?>
              <option value="<?= (int)$t['id'] ?>" <?= $selectedOpt($selected['category'] ?? [], (int)$t['id']) ?>>
                <?= $h((string)$t['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Podrž Ctrl/⌘ pro vícenásobný výběr.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Štítky</label>
          <select class="form-select" name="tags[]" multiple size="6">
            <?php foreach (($terms['tag'] ?? []) as $t): ?>
              <option value="<?= (int)$t['id'] ?>" <?= $selectedOpt($selected['tag'] ?? [], (int)$t['id']) ?>>
                <?= $h((string)$t['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Podrž Ctrl/⌘ pro vícenásobný výběr.</div>
        </div>
      </div>

      <div class="mb-3 mt-3">
        <label class="form-label">Obsah</label>
        <textarea class="form-control" name="content" rows="8"><?= $isEdit ? $h((string)($post['content'] ?? '')) : '' ?></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label">Thumbnail</label>
        <input class="form-control" type="file" name="thumbnail" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,image/*,application/pdf">
        <div class="form-text">Uloží se do <code>uploads/Y/m/posts/</code>.</div>
        <?php if ($isEdit && !empty($post['thumbnail_id'])):
          $thumb = \Core\Database\Init::query()->table('media')->select(['id','url','mime'])->where('id','=',(int)$post['thumbnail_id'])->first();
          if ($thumb): ?>
            <div class="mt-2">
              <?php if (str_starts_with((string)$thumb['mime'], 'image/')): ?>
                <img src="<?= $h((string)$thumb['url']) ?>" alt="thumb" style="max-width:240px;border-radius:.5rem">
              <?php else: ?>
                <a class="btn btn-outline-primary btn-sm" target="_blank" href="<?= $h((string)$thumb['url']) ?>">Otevřít soubor</a>
              <?php endif; ?>
              <div class="small text-secondary mt-1"><?= $h((string)$thumb['mime']) ?></div>
            </div>
        <?php endif; endif; ?>
      </div>

      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
    </div>
    <div class="card-footer d-flex gap-2">
      <button class="btn btn-primary" type="submit"><?= $h($isEdit ? 'Uložit změny' : 'Vytvořit') ?></button>
      <a class="btn btn-outline-secondary" href="<?= $h('admin.php?'.http_build_query(['r'=>'posts','type'=>$type])) ?>">Zpět na seznam</a>
    </div>
  </form>
<?php
});
