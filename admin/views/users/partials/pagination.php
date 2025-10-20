<?php
declare(strict_types=1);
/** @var array{page:int,per_page:int,total:int,pages:int} $pagination */
/** @var callable $buildUrl */
?>
<div data-users-pagination>
  <?php $this->render('parts/listing/pagination', [
    'page'      => (int)($pagination['page'] ?? 1),
    'pages'     => (int)($pagination['pages'] ?? 1),
    'buildUrl'  => $buildUrl,
    'ariaLabel' => 'Stránkování',
  ]); ?>
</div>
