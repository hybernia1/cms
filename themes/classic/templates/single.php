<?php
/** @var \Cms\View\Assets $assets */
/** @var string $siteTitle */
/** @var array{id:int,title:string,content:?string,created_at:string,author_id:int,type:string,slug:string,comments_allowed?:int} $post */
/** @var array<int,array> $commentsTree */
/** @var bool $commentsAllowed */
/** @var string $csrfPublic */
/** @var array|null $commentFlash */
/** @var array|null $frontUser */
/** @var array<string,array<int,array{id:mixed,slug:string,name:string,type:string}>> $termsByType */

$this->render('layouts/base', compact('assets', 'siteTitle'), function() use ($post,$commentsTree,$commentsAllowed,$csrfPublic,$commentFlash,$frontUser,$termsByType) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $cs = new \Cms\Settings\CmsSettings();

?>
  <article class="card mb-3">
    <div class="card-body">
      <h2 style="margin-top:0"><?= $h($post['title']) ?></h2>
      <div class="meta">Publikováno: <?= $h($cs->formatDateTime(new \DateTimeImmutable((string)$post['created_at']))) ?></div>
      <?php
        $categories = $termsByType['category'] ?? [];
        $tags       = $termsByType['tag'] ?? [];
      ?>
      <?php if ($categories || $tags): ?>
        <div class="meta" style="margin-top:0.5rem; display:flex; flex-direction:column; gap:0.25rem;">
          <?php if ($categories): ?>
            <div><strong>Kategorie:</strong>
              <?php foreach ($categories as $idx => $category): ?>
                <a href="<?= $h($urls->category((string)$category['slug'])) ?>"><?= $h((string)$category['name']) ?></a><?= $idx < count($categories) - 1 ? ', ' : '' ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if ($tags): ?>
            <div><strong>Štítky:</strong>
              <?php foreach ($tags as $idx => $tag): ?>
                <a href="<?= $h($urls->tag((string)$tag['slug'])) ?>"><?= $h((string)$tag['name']) ?></a><?= $idx < count($tags) - 1 ? ', ' : '' ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <div style="margin-top:1rem;white-space:pre-wrap"><?= nl2br($h((string)($post['content'] ?? ''))) ?></div>
    </div>
  </article>

  <?php if ($commentsAllowed): ?>
    <section class="mb-3">
      <h3 class="h5 mb-2">Komentáře</h3>
      <?php $this->part('parts/comments/list', ['commentsTree'=>$commentsTree]); ?>
    </section>

    <section class="mb-3">
      <?php $this->part('parts/comments/form', [
        'postId'       => (int)$post['id'],
        'csrfPublic'   => $csrfPublic,
        'commentFlash' => $commentFlash,
        'frontUser'    => $frontUser,
      ]); ?>
    </section>
  <?php endif; ?>
<?php }); ?>
