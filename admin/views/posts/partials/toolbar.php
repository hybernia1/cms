<?php
declare(strict_types=1);

/**
 * @var array{type:string,status:string,author:string,q:string} $filters
 * @var string $type
 * @var array<string,array<string,string>> $types
 * @var \Cms\Admin\Utils\LinkGenerator $urls
 * @var array<string,int> $statusCounts
 * @var callable $buildUrl
 */

$filters = array_merge([
    'type'   => 'post',
    'status' => '',
    'author' => '',
    'q'      => '',
], $filters ?? []);

$type = (string)$type;
$types = is_array($types ?? null) ? $types : [];
$statusCounts = is_array($statusCounts ?? null) ? $statusCounts : [];
$buildUrl = $buildUrl ?? static fn (array $override = []): string => '#';

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$typeCfg = $types[$type] ?? ['create' => 'Nový příspěvek'];
$status = (string)$filters['status'];
$q = (string)$filters['q'];

$totalCount = (int)($statusCounts['__total'] ?? 0);
if ($totalCount === 0 && $statusCounts !== []) {
    $totalCount = array_sum(array_map(static fn ($v) => is_int($v) ? $v : 0, $statusCounts));
}

$statusTabs = [
    ''        => 'Vše',
    'publish' => 'Publikované',
    'draft'   => 'Koncepty',
];

$statusCountFor = static function (string $value) use ($statusCounts, $totalCount): int {
    if ($value === '') {
        return $totalCount;
    }
    return (int)($statusCounts[$value] ?? 0);
};

$tabLinks = [];
foreach ($statusTabs as $value => $label) {
    $tabLinks[] = [
        'label'  => $label,
        'href'   => $buildUrl(['status' => $value]),
        'active' => $status === $value,
        'count'  => $statusCountFor($value),
    ];
}

$this->render('parts/listing/toolbar', [
    'tabs'      => $tabLinks,
    'tabsClass' => 'order-2 order-md-1',
    'search'    => [
        'action'        => 'admin.php',
        'wrapperClass'  => 'order-1 order-md-2 ms-md-auto',
        'hidden'        => [
            'r'    => 'posts',
            'type' => $type,
            'status' => $status,
        ],
        'value'         => $q,
        'placeholder'   => 'Hledat…',
        'resetHref'     => $buildUrl(['q' => '']),
        'resetDisabled' => $q === '',
        'searchTooltip' => 'Hledat',
        'clearTooltip'  => 'Zrušit filtr',
    ],
    'button'    => [
        'href'  => 'admin.php?' . http_build_query(['r' => 'posts', 'a' => 'create', 'type' => $type]),
        'label' => (string)($typeCfg['create'] ?? 'Nový záznam'),
        'icon'  => 'bi bi-plus-lg',
        'class' => 'btn btn-success btn-sm order-3',
    ],
]);
