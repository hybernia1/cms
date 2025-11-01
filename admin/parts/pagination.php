<?php
declare(strict_types=1);

/**
 * @var array{page:int,per_page:int,total:int,pages:int} $pagination
 * @var callable $buildUrl
 * @var string|null $ariaLabel
 */

$page = (int)($pagination['page'] ?? 1);
$pages = (int)($pagination['pages'] ?? 1);
$ariaLabel = $ariaLabel ?? 'Stránkování';

$this->render('parts/listing/pagination', [
    'page'      => $page,
    'pages'     => $pages,
    'buildUrl'  => $buildUrl,
    'ariaLabel' => $ariaLabel,
]);
