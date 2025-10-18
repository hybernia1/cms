<?php
/** @var array<string,mixed> $post */
/** @var array<int,array<string,mixed>> $commentsTree */
/** @var bool $commentsAllowed */
/** @var string $csrfPublic */
/** @var array<string,string>|null $commentFlash */
/** @var array<string,mixed>|null $frontUser */
/** @var array<string,array<int,array<string,mixed>>> $termsByType */
/** @var \Cms\Utils\LinkGenerator $urls */

$cs = new \Cms\Settings\CmsSettings();
$categories = $termsByType['category'] ?? [];
$tags       = $termsByType['tag'] ?? [];
?>
<article class="card card--article">
  <header class="article__header">
    <h1 class="article__title"><?= e((string)($post['title'] ?? 'Článek')) ?></h1>
    <div class="article__meta">Publikováno: <?= e($cs->formatDateTime(new \DateTimeImmutable((string)($post['created_at'] ?? 'now')))) ?></div>
    <?php if ($categories || $tags): ?>
      <div class="article__terms">
        <?php foreach ($categories as $cat): ?>
          <a class="article__badge" href="<?= e($urls->category((string)$cat['slug'])) ?>">#<?= e((string)$cat['name']) ?></a>
        <?php endforeach; ?>
        <?php foreach ($tags as $tag): ?>
          <a class="article__badge article__badge--outline" href="<?= e($urls->tag((string)$tag['slug'])) ?>">#<?= e((string)$tag['name']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </header>
  <div class="article__content">
    <?= nl2br(e((string)($post['content'] ?? ''))) ?>
  </div>
</article>

<?php if ($commentsAllowed): ?>
  <section class="card card--section">
    <header class="card__header">
      <h2 class="card__title">Diskuse</h2>
    </header>
    <?php $this->part('comments', 'list', [
      'commentsTree' => $commentsTree,
      'threadId'     => 'post-' . (int)($post['id'] ?? 0),
      'classes'      => [
        'empty'       => 'muted',
        'replyButton' => 'comment__reply btn btn--link',
      ],
    ]); ?>
  </section>
  <?php $this->part('comments', 'form', [
    'postId'       => (int)($post['id'] ?? 0),
    'csrfPublic'   => $csrfPublic,
    'commentFlash' => $commentFlash,
    'frontUser'    => $frontUser,
    'urls'         => $urls,
    'threadId'     => 'post-' . (int)($post['id'] ?? 0),
    'classes'      => [
      'replyInfo'   => 'comment-form__reply-info alert alert--info',
      'replyLabel'  => 'comment-form__reply-label',
      'replyTarget' => 'comment-form__reply-target',
      'replyCancel' => 'comment-form__reply-cancel btn btn--link',
    ],
  ]); ?>
<?php endif; ?>
