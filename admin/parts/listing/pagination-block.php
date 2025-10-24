<?php
declare(strict_types=1);

/**
 * @var array{page:int,per_page:int,total:int,pages:int} $pagination
 * @var callable $buildUrl
 * @var array<string,scalar|null>|null $wrapperAttributes
 * @var string|null $ariaLabel
 */

$wrapperAttributes = is_array($wrapperAttributes ?? null) ? $wrapperAttributes : [];
$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$attributes = '';
foreach ($wrapperAttributes as $attribute => $value) {
    $attribute = trim((string)$attribute);
    if ($attribute === '') {
        continue;
    }

    if ($value === null || $value === '') {
        $attributes .= ' ' . $attribute;
        continue;
    }

    $attributes .= sprintf(' %s="%s"', $attribute, $h((string)$value));
}

$build = is_callable($buildUrl ?? null)
    ? $buildUrl
    : static fn(array $params = []): string => '#';
?>
<div<?= $attributes ?>>
  <?php $this->render('parts/listing/pagination', [
    'page'      => (int)($pagination['page'] ?? 1),
    'pages'     => (int)($pagination['pages'] ?? 1),
    'buildUrl'  => $build,
    'ariaLabel' => $ariaLabel ?? 'Stránkování',
  ]); ?>
</div>
