<?php
declare(strict_types=1);

/**
 * @var array{page:int,per_page:int,total:int,pages:int} $pagination
 * @var callable $buildUrl
 */

$pagination = $pagination ?? ['page' => 1, 'per_page' => 15, 'total' => 0, 'pages' => 1];

$this->render('parts/listing/pagination', [
    'page'      => (int)($pagination['page'] ?? 1),
    'pages'     => (int)($pagination['pages'] ?? 1),
    'buildUrl'  => $buildUrl ?? static fn (): string => '#',
    'ariaLabel' => 'Stránkování',
]);
