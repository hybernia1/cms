<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array $filters */
/** @var array<int,array> $items */
/** @var array{page:int,per_page:int,total:int,pages:int} $pagination */
/** @var string $csrf */

$this->render('layouts/base', compact('pageTitle','nav','currentUser'), function () use ($flash,$filters,$items,$pagination,$csrf) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $badge = function(string $status): string {
    return match($status){
      'published' => 'success',
      'spam'      => 'danger',
      default     => 'secondary'
    };
  };
?>
  <?php if ($flash): ?>
    <div class="alert alert-<?= $h((string)$flash['type']) ?>"><?= $h((string)$flash['msg']) ?></div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-body">
      <form class="row g-2" method="get" action="admin.php">
        <input type="hidden" name="r" value="comments">
        <div class="col-md-3">
          <select class="form-select" name="status">
            <option value="">— všechny stavy —</option>
            <?php foreach (['draft'=>'Koncept','published'=>'Schválené','spam'=>'Spam'] as $val=>$lbl): ?>
              <option value="<?= $val ?>" <?= $filters['status']===$val?'selected':'' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-5">
          <input class="form-control" name="q" placeholder="Hledat v textu/jménu/e-mailu…" value="<?= $h((string)$filters['q']) ?>">
        </div>
        <div class="col-md-4">
          <div class="input-group">
            <span class="input-group-text">Post</span>
            <input class="form-control" name="post" placeholder="slug nebo ID" value="<?= $h((string)$filters['post']) ?>">
            <button class="btn btn-primary" type="submit">Filtrovat</button>
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
            <th>Autor / E-mail</th>
            <th>Text</th>
            <th>Post</th>
            <th style="width:130px">Stav</th>
            <th style="width:240px"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $c): ?>
            <tr>
              <td>#<?= $h((string)$c['id']) ?></td>
              <td>
                <div class="fw-semibold"><?= $h((string)($c['author_name'] ?? '')) ?></div>
                <div class="small text-secondary"><?= $h((string)($c['author_email'] ?? '')) ?></div>
              </td>
              <td>
                <div class="text-truncate" style="max-width:420px"><?= $h(mb_substr((string)$c['content'],0,160)) ?><?= mb_strlen((string)$c['content'])>160?'…':'' ?></div>
                <div class="small text-secondary"><?= $h((string)$c['created_at']) ?></div>
              </td>
              <td>
                <div class="small"><span class="badge text-bg-info"><?= $h((string)$c['post_type']) ?></span></div>
                <a class="small" href="admin.php?r=posts&a=edit&id=<?= (int)$c['post_id'] ?>">#<?= (int)$c['post_id'] ?></a>
                <div class="small text-secondary"><?= $h((string)$c['post_title']) ?></div>
              </td>
              <td>
                <span class="badge text-bg-<?= $badge((string)$c['status']) ?>"><?= $h((string)$c['status']) ?></span>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="admin.php?r=comments&a=show&id=<?= (int)$c['id'] ?>">Detail</a>

                <form method="post" action="admin.php?r=comments&a=approve" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <input type="hidden" name="_back" value="<?= $h($_SERVER['REQUEST_URI'] ?? 'admin.php?r=comments') ?>">
                  <button class="btn btn-sm btn-outline-success" type="submit">Schválit</button>
                </form>

                <form method="post" action="admin.php?r=comments&a=draft" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <input type="hidden" name="_back" value="<?= $h($_SERVER['REQUEST_URI'] ?? 'admin.php?r=comments') ?>">
                  <button class="btn btn-sm btn-outline-secondary" type="submit">Koncept</button>
                </form>

                <form method="post" action="admin.php?r=comments&a=spam" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <input type="hidden" name="_back" value="<?= $h($_SERVER['REQUEST_URI'] ?? 'admin.php?r=comments') ?>">
                  <button class="btn btn-sm btn-outline-warning" type="submit">Spam</button>
                </form>

                <form method="post" action="admin.php?r=comments&a=delete" style="display:inline" onsubmit="return confirm('Opravdu smazat? Smaže i odpovědi.');">
                  <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <input type="hidden" name="_back" value="<?= $h($_SERVER['REQUEST_URI'] ?? 'admin.php?r=comments') ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Smazat</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$items): ?>
            <tr><td colspan="6" class="text-center text-secondary py-4">Žádné komentáře</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (($pagination['pages'] ?? 1) > 1): ?>
    <nav class="mt-3">
      <ul class="pagination">
        <?php
          $page = (int)$pagination['page']; $pages = (int)$pagination['pages'];
          $qs = $_GET; unset($qs['page']); $base = 'admin.php?'.http_build_query(array_merge(['r'=>'comments'], $qs));
        ?>
        <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= $base.'&page='.max(1,$page-1) ?>">‹</a></li>
        <?php for($i=1;$i<=$pages;$i++): ?>
          <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="<?= $base.'&page='.$i ?>"><?= $i ?></a></li>
        <?php endfor; ?>
        <li class="page-item <?= $page>=$pages?'disabled':'' ?>"><a class="page-link" href="<?= $base.'&page='.min($pages,$page+1) ?>">›</a></li>
      </ul>
    </nav>
  <?php endif; ?>
<?php
});
