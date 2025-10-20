<?php
declare(strict_types=1);
/** @var array $filters */
/** @var string $type */
/** @var array $types */
/** @var callable $buildUrl */

$typeConfig = $types[$type] ?? ['create' => 'Nový term'];
$queryValue = (string)($filters['q'] ?? '');
?>
<div data-terms-toolbar>
  <?php $this->render('parts/listing/toolbar', [
    'search' => [
      'action'        => 'admin.php',
      'wrapperClass'  => 'order-1 flex-grow-1',
      'hidden'        => ['r' => 'terms', 'type' => $type],
      'value'         => $queryValue,
      'placeholder'   => 'Hledat…',
      'resetHref'     => $buildUrl(['q' => '']),
      'resetDisabled' => $queryValue === '',
      'searchTooltip' => 'Hledat',
      'clearTooltip'  => 'Zrušit filtr',
    ],
    'button' => [
      'href'  => 'admin.php?' . http_build_query(['r' => 'terms', 'a' => 'create', 'type' => $type]),
      'label' => (string)($typeConfig['create'] ?? 'Nový term'),
      'icon'  => 'bi bi-plus-lg',
      'class' => 'btn btn-success btn-sm order-2 order-md-2 ms-md-auto',
    ],
  ]); ?>
</div>
