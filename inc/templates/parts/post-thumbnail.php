<?php
declare(strict_types=1);

/**
 * @var array<string,mixed>|null $thumbnail
 * @var string|null $title
 * @var string|null $class
 * @var string|null $imgClass
 * @var string|null $loading
 * @var string|null $decoding
 */

$thumbnail = is_array($thumbnail ?? null) ? $thumbnail : null;
if ($thumbnail === null) {
    return;
}

$url = (string)($thumbnail['url'] ?? '');
if ($url === '') {
    return;
}

$webpUrl = isset($thumbnail['webpUrl']) && is_string($thumbnail['webpUrl']) && $thumbnail['webpUrl'] !== ''
    ? (string)$thumbnail['webpUrl']
    : null;

$width  = isset($thumbnail['width']) && (int)$thumbnail['width'] > 0 ? (int)$thumbnail['width'] : null;
$height = isset($thumbnail['height']) && (int)$thumbnail['height'] > 0 ? (int)$thumbnail['height'] : null;

$title = isset($title) ? trim((string)$title) : '';
if ($title === '' && isset($post) && is_array($post)) {
    $title = trim((string)($post['title'] ?? ''));
}

$alt = isset($thumbnail['alt']) ? trim((string)$thumbnail['alt']) : $title;
if ($alt === '') {
    $alt = 'Obrázek příspěvku';
}

$classAttr = 'post-thumbnail';
if (isset($class) && (string)$class !== '') {
    $classAttr .= ' ' . trim((string)$class);
}

$imgClassAttr = isset($imgClass) && (string)$imgClass !== '' ? trim((string)$imgClass) : null;
$loadingAttr  = isset($loading) && (string)$loading !== '' ? (string)$loading : 'lazy';
$decodingAttr = isset($decoding) && (string)$decoding !== '' ? (string)$decoding : 'async';

$esc = static fn(string $value): string => e($value);
?>
<figure class="<?= $esc($classAttr) ?>">
  <picture>
    <?php if ($webpUrl && $webpUrl !== $url): ?>
      <source srcset="<?= $esc($webpUrl) ?>" type="image/webp">
    <?php endif; ?>
    <img
      src="<?= $esc($url) ?>"
      alt="<?= $esc($alt) ?>"
      <?php if ($imgClassAttr): ?>class="<?= $esc($imgClassAttr) ?>" <?php endif; ?>
      loading="<?= $esc($loadingAttr) ?>"
      decoding="<?= $esc($decodingAttr) ?>"
      <?php if ($width !== null): ?>width="<?= $width ?>" <?php endif; ?>
      <?php if ($height !== null): ?>height="<?= $height ?>"<?php endif; ?>
    >
  </picture>
</figure>
