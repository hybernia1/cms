<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array $comment */
/** @var array<int,array> $children */
/** @var string $csrf */

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($comment,$children,$csrf) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $badge = function(string $status): string {
    return match($status){
      'published' => 'success',
      'spam'      => 'danger',
      default     => 'secondary'
    };
  };
  $back = 'admin.php?r=comments';
?>
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h5 m-0">Komentář #<?= (int)$comment['id'] ?></h2>
    <a class="btn btn-outline-secondary" href="<?= $back ?>">Zpět na seznam</a>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between">
        <div>
          <div class="fw-semibold"><?= $h((string)($comment['author_name'] ?? '')) ?></div>
          <div class="small text-secondary"><?= $h((string)($comment['author_email'] ?? '')) ?></div>
        </div>
        <div>
          <span class="badge text-bg-<?= $badge((string)$comment['status']) ?>"><?= $h((string)$comment['status']) ?></span>
        </div>
      </div>
      <div class="small text-secondary mt-1"><?= $h((string)$comment['created_at']) ?> • k postu: <a href="admin.php?r=posts&a=edit&id=<?= (int)$comment['post_id'] ?>">#<?= (int)$comment['post_id'] ?></a></div>
      <hr>
      <div style="white-space:pre-wrap"><?= nl2br($h((string)$comment['content'])) ?></div>
    </div>
    <div class="card-footer d-flex gap-2">
      <form method="post" action="admin.php?r=comments&a=approve">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int)$comment['id'] ?>">
        <input type="hidden" name="_back" value="<?= 'admin.php?r=comments&a=show&id='.(int)$comment['id'] ?>">
        <button class="btn btn-success btn-sm" type="submit">Schválit</button>
      </form>
      <form method="post" action="admin.php?r=comments&a=draft">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int)$comment['id'] ?>">
        <input type="hidden" name="_back" value="<?= 'admin.php?r=comments&a=show&id='.(int)$comment['id'] ?>">
        <button class="btn btn-secondary btn-sm" type="submit">Koncept</button>
      </form>
      <form method="post" action="admin.php?r=comments&a=spam">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int)$comment['id'] ?>">
        <input type="hidden" name="_back" value="<?= 'admin.php?r=comments&a=show&id='.(int)$comment['id'] ?>">
        <button class="btn btn-warning btn-sm" type="submit">Spam</button>
      </form>
      <form method="post" action="admin.php?r=comments&a=delete" onsubmit="return confirm('Opravdu smazat? Smaže i odpovědi.');">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int)$comment['id'] ?>">
        <input type="hidden" name="_back" value="<?= $back ?>">
        <button class="btn btn-danger btn-sm" type="submit">Smazat</button>
      </form>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">Odpovědět</div>
    <div class="card-body">
      <form method="post" action="admin.php?r=comments&a=reply">
        <textarea class="form-control mb-2" name="content" rows="4" placeholder="Napiš odpověď…"></textarea>
        <input type="hidden" name="parent_id" value="<?= (int)$comment['id'] ?>">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <button class="btn btn-primary" type="submit">Odeslat odpověď</button>
      </form>
    </div>
  </div>

  <?php if ($children): ?>
    <div class="card">
      <div class="card-header">Odpovědi (<?= count($children) ?>)</div>
      <div class="list-group list-group-flush">
        <?php foreach ($children as $ch): ?>
          <div class="list-group-item">
            <div class="d-flex justify-content-between">
              <div class="fw-semibold"><?= $h((string)($ch['author_name'] ?? '')) ?></div>
              <div class="small text-secondary"><?= $h((string)$ch['created_at']) ?></div>
            </div>
            <div style="white-space:pre-wrap"><?= nl2br($h((string)$ch['content'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
<?php
});
