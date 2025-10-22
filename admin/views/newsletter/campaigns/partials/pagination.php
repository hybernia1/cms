<?php
declare(strict_types=1);

/**
 * @var array{page:int,pages:int,per_page:int,total:int} $pagination
 * @var callable $buildUrl
 */

$pagination = array_merge(['page' => 1, 'pages' => 1, 'per_page' => 20, 'total' => 0], $pagination ?? []);
$buildUrl = $buildUrl ?? static fn (array $override = []): string => '#';

$this->render('parts/listing/pagination', [
    'page'      => (int)$pagination['page'],
    'pages'     => (int)$pagination['pages'],
    'buildUrl'  => $buildUrl,
    'ariaLabel' => 'Stránkování kampaní',
]);
