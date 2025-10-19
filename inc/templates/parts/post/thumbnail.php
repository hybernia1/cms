<?php
declare(strict_types=1);
/**
 * @var array<string,mixed>|string|null $image   Odkaz na obrázek nebo pole s klíči url/src, alt, width, height, srcset, sizes
 * @var string|null $link                       Volitelná URL obalující obrázek
 * @var array<string,string>|null $classes      CSS třídy přizpůsobitelné z venku
 * @var array<string,string>|null $attributes   Dodatečné HTML atributy pro <figure>
 * @var array<string,string>|null $linkAttrs    Dodatečné HTML atributy pro <a>
 * @var bool|null $lazy                         Zda má být využito loading="lazy"
 */

$image = $image ?? null;
if (is_string($image)) {
    $image = ['url' => $image];
}
$image = is_array($image) ? $image : null;

$link = isset($link) ? trim((string) $link) : null;
$classes = is_array($classes ?? null) ? $classes : [];
$attributes = is_array($attributes ?? null) ? $attributes : [];
$linkAttrs = is_array($linkAttrs ?? null) ? $linkAttrs : [];
$lazy = isset($lazy) ? (bool) $lazy : true;

if (!$image) {
    return;
}

$url = (string) ($image['url'] ?? $image['src'] ?? '');
if ($url === '') {
    return;
}

$alt = isset($image['alt']) ? (string) $image['alt'] : '';
$width = isset($image['width']) ? (int) $image['width'] : null;
$height = isset($image['height']) ? (int) $image['height'] : null;
$srcset = isset($image['srcset']) ? trim((string) $image['srcset']) : '';
$sizes = isset($image['sizes']) ? trim((string) $image['sizes']) : '';

$esc = static fn(string $value): string => e($value);
$cls = static function (string $key) use ($classes): string {
    $defaults = [
        'wrapper' => 'post-thumbnail',
        'link'    => 'post-thumbnail__link',
        'image'   => 'post-thumbnail__image',
    ];

    return trim((string) ($classes[$key] ?? $defaults[$key] ?? ''));
};

$attrStr = static function (array $attrs) use ($esc): string {
    $chunks = [];
    foreach ($attrs as $attr => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $chunks[] = sprintf('%s="%s"', $esc($attr), $esc((string) $value));
    }
    return $chunks ? ' ' . implode(' ', $chunks) : '';
};

$figureAttrs = $attrStr($attributes);
$linkAttributes = $linkAttrs;
if ($link) {
    $linkAttributes['href'] = $link;
    if (!isset($linkAttributes['class'])) {
        $linkAttributes['class'] = $cls('link');
    } elseif (trim($linkAttributes['class']) !== '') {
        $linkAttributes['class'] = trim($linkAttributes['class'] . ' ' . $cls('link'));
    }
}

$imageAttrs = [
    'class'   => $cls('image'),
    'src'     => $url,
    'alt'     => $alt,
];
if ($width) { $imageAttrs['width'] = (string) $width; }
if ($height) { $imageAttrs['height'] = (string) $height; }
if ($srcset !== '') { $imageAttrs['srcset'] = $srcset; }
if ($sizes !== '') { $imageAttrs['sizes'] = $sizes; }
if ($lazy) { $imageAttrs['loading'] = 'lazy'; }

?>
<figure class="<?= $esc($cls('wrapper')) ?>"<?= $figureAttrs ?>>
  <?php if ($link): ?>
    <a<?= $attrStr($linkAttributes) ?>>
      <img<?= $attrStr($imageAttrs) ?>>
    </a>
  <?php else: ?>
    <img<?= $attrStr($imageAttrs) ?>>
  <?php endif; ?>
</figure>

