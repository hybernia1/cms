<?php
declare(strict_types=1);
/** @var array{status:string,q:string,post:string} $filters */
/** @var array{page:int,per_page:int,total:int,pages:int} $pagination */
/** @var string $csrf */
/** @var \Cms\Admin\View\Listing\BulkConfig $bulkConfig */

?>
<div data-comments-bulk>
  <?php $this->render('parts/listing/bulk-form', $bulkConfig->formParams()); ?>
</div>
