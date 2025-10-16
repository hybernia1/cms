<?php
/** @var array<string,mixed> $post */
/** @var array<int,array<string,mixed>> $commentsTree */
/** @var bool $commentsAllowed */
/** @var string $csrfPublic */
/** @var array<string,string>|null $commentFlash */
/** @var array<string,mixed>|null $frontUser */
/** @var array<string,array<int,array<string,mixed>>> $termsByType */
/** @var \Cms\Utils\LinkGenerator $urls */

$h  = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$cs = new \Cms\Settings\CmsSettings();
$categories = $termsByType['category'] ?? [];
$tags       = $termsByType['tag'] ?? [];
?>
<article class="card card--article">
  <header class="article__header">
    <h1 class="article__title"><?= $h((string)($post['title'] ?? 'Článek')) ?></h1>
    <div class="article__meta">Publikováno: <?= $h($cs->formatDateTime(new \DateTimeImmutable((string)($post['created_at'] ?? 'now')))) ?></div>
    <?php if ($categories || $tags): ?>
      <div class="article__terms">
        <?php foreach ($categories as $cat): ?>
          <a class="article__badge" href="<?= $h($urls->category((string)$cat['slug'])) ?>">#<?= $h((string)$cat['name']) ?></a>
        <?php endforeach; ?>
        <?php foreach ($tags as $tag): ?>
          <a class="article__badge article__badge--outline" href="<?= $h($urls->tag((string)$tag['slug'])) ?>">#<?= $h((string)$tag['name']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </header>
  <div class="article__content">
    <?= nl2br($h((string)($post['content'] ?? ''))) ?>
  </div>
</article>

<?php if ($commentsAllowed): ?>
  <section class="card card--section">
    <header class="card__header">
      <h2 class="card__title">Diskuse</h2>
    </header>
    <?php $this->part('parts/comments/list', ['commentsTree' => $commentsTree]); ?>
  </section>
  <?php $this->part('parts/comments/form', [
    'postId'       => (int)($post['id'] ?? 0),
    'csrfPublic'   => $csrfPublic,
    'commentFlash' => $commentFlash,
    'frontUser'    => $frontUser,
    'urls'         => $urls,
  ]); ?>
<?php endif; ?>
