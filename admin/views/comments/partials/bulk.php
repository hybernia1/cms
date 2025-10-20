<?php
declare(strict_types=1);
/** @var array{status:string,q:string,post:string} $filters */
/** @var array{page:int,per_page:int,total:int,pages:int} $pagination */
/** @var string $csrf */

$status = (string)($filters['status'] ?? '');
$q = (string)($filters['q'] ?? '');
$postFilter = (string)($filters['post'] ?? '');
$page = (int)($pagination['page'] ?? 1);
?>
<div data-comments-bulk>
  <?php $this->render('parts/listing/bulk-form', [
    'formId'       => 'comments-bulk-form',
    'action'       => 'admin.php?r=comments&a=bulk',
    'csrf'         => $csrf,
    'selectAll'    => '#comments-select-all',
    'rowSelector'  => '.comment-row-check',
    'actionSelect' => '#comments-bulk-select',
    'applyButton'  => '#comments-bulk-apply',
    'counter'      => '#comments-bulk-counter',
    'hidden'       => [
      'status' => $status,
      'q'      => $q,
      'post'   => $postFilter,
      'page'   => (string)$page,
    ],
  ]); ?>
</div>
