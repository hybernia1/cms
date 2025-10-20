<?php
declare(strict_types=1);
/** @var array{status:string,q:string,post:string} $filters */
/** @var array<string,int>|null $statusCounts */
/** @var callable $buildUrl */

$statusCounts = is_array($statusCounts ?? null) ? $statusCounts : [];
$totalCount = (int)($statusCounts['__total'] ?? 0);
if ($totalCount === 0 && $statusCounts !== []) {
    $totalCount = array_sum(array_map(static fn($v) => is_int($v) ? $v : 0, $statusCounts));
}
$statusCountFor = static function (string $value) use ($statusCounts, $totalCount): int {
    if ($value === '') {
        return $totalCount;
    }
    return (int)($statusCounts[$value] ?? 0);
};

$status = (string)($filters['status'] ?? '');
$q = (string)($filters['q'] ?? '');
$postFilter = (string)($filters['post'] ?? '');

$tabs = [
    ''          => 'Vše',
    'draft'     => 'Koncepty',
    'published' => 'Schválené',
    'spam'      => 'Spam',
];

$tabLinks = [];
foreach ($tabs as $value => $label) {
    $tabLinks[] = [
        'label'  => $label,
        'href'   => $buildUrl(['status' => $value !== '' ? $value : null, 'page' => null]),
        'active' => $status === $value,
        'count'  => $statusCountFor($value),
    ];
}
?>
<div data-comments-toolbar>
  <?php $this->render('parts/listing/toolbar', [
    'tabs'   => $tabLinks,
    'search' => [
      'action'        => 'admin.php',
      'wrapperClass'  => 'order-1 order-md-2 ms-md-auto',
      'hidden'        => ['r' => 'comments', 'status' => $status, 'post' => $postFilter],
      'value'         => $q,
      'placeholder'   => 'Text komentáře, autor, e-mail…',
      'resetHref'     => $buildUrl(['q' => null, 'page' => null]),
      'resetDisabled' => $q === '',
      'searchTooltip' => 'Hledat',
      'clearTooltip'  => 'Zrušit filtr',
    ],
  ]); ?>
</div>
