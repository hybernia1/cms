<?php
declare(strict_types=1);
/**
 * @var array<string,mixed> $bulkForm
 */

$bulkForm = is_array($bulkForm ?? null) ? $bulkForm : [];
$hidden = isset($bulkForm['hidden']) && is_array($bulkForm['hidden']) ? $bulkForm['hidden'] : [];
?>
<div data-admin-fragment="posts-bulk-form">
  <?php $this->render('parts/listing/bulk-form', [
    'formId' => (string)($bulkForm['formId'] ?? 'posts-bulk-form'),
    'action' => (string)($bulkForm['action'] ?? ''),
    'csrf' => (string)($bulkForm['csrf'] ?? ''),
    'selectAll' => (string)($bulkForm['selectAll'] ?? '#select-all'),
    'rowSelector' => (string)($bulkForm['rowSelector'] ?? '.row-check'),
    'actionSelect' => (string)($bulkForm['actionSelect'] ?? '#bulk-action-select'),
    'applyButton' => (string)($bulkForm['applyButton'] ?? '#bulk-apply'),
    'counter' => (string)($bulkForm['counter'] ?? '#bulk-selection-counter'),
    'hidden' => $hidden,
    'ajaxAction' => isset($bulkForm['ajaxAction']) && is_string($bulkForm['ajaxAction']) ? $bulkForm['ajaxAction'] : null,
  ]); ?>
</div>
