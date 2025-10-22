<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array $comment */
/** @var array<int,array> $children */
/** @var string $csrf */
/** @var int $replyParentId */

$this->render('parts/layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($comment,$children,$csrf,$replyParentId) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $badge = function(string $status): string {
    return match($status){
      'published' => 'success',
      'spam'      => 'danger',
      default     => 'secondary'
    };
  };
  $back = 'admin.php?r=comments';
  $postId = (int)($comment['post_id'] ?? 0);
  $postType = (string)($comment['post_type'] ?? '');
  $postUrl = 'admin.php?r=posts&a=edit&id=' . $postId;
  if ($postType !== '') {
    $postUrl .= '&type=' . rawurlencode($postType);
  }
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
          <span class="badge text-bg-<?= $badge((string)$comment['status']) ?>" data-comment-status-badge><?= $h((string)$comment['status']) ?></span>
        </div>
      </div>
      <div class="small text-secondary mt-1"><?= $h((string)$comment['created_at']) ?> • k postu: <a href="<?= $h($postUrl) ?>">#<?= $postId ?></a></div>
      <hr>
      <div style="white-space:pre-wrap"><?= nl2br($h((string)$comment['content'])) ?></div>
    </div>
    <div class="card-footer">
      <?php $this->render('parts/comments/actions', [
        'comment'          => $comment,
        'csrf'             => $csrf,
        'backUrl'          => 'admin.php?r=comments&a=show&id=' . (int)$comment['id'],
        'deleteBackUrl'    => $back,
        'wrapperClass'     => 'd-flex flex-wrap gap-2',
        'statusActions'    => ['approve', 'draft', 'spam'],
        'statusButtonClass'=> '',
        'deleteButtonClass'=> '',
        'deleteButtonTooltip' => 'Smazat',
      ]); ?>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">Odpovědět</div>
    <div class="card-body">
      <form method="post" action="admin.php?r=comments&a=reply" data-ajax data-comments-action="reply">
        <textarea class="form-control mb-2" name="content" rows="4" placeholder="Napiš odpověď…"></textarea>
        <input type="hidden" name="parent_id" value="<?= (int)$replyParentId ?>">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <?php if ((int)$comment['id'] !== (int)$replyParentId): ?>
          <div class="form-text mb-2">Odpověď bude připojena k hlavnímu komentáři #<?= (int)$replyParentId ?>.</div>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit">Odeslat odpověď</button>
      </form>
    </div>
  </div>

  <?php $this->render('comments/partials/thread', [
    'children' => $children,
  ]); ?>
<?php
});
