<?php
/** @var \Cms\View\Assets $assets */
/** @var string $siteTitle */
/** @var array<int,array{id:mixed,slug:string,name:string,type:string,description:?string,created_at:string,posts_count:mixed}> $terms */
/** @var array<int,string> $availableTypes */
/** @var string|null $activeType */

$this->render('layouts/base', compact('assets', 'siteTitle'), function() use ($terms, $availableTypes, $activeType) {
    $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $formatType = static function(string $type): string {
        return function_exists('mb_convert_case')
            ? mb_convert_case($type, MB_CASE_TITLE, 'UTF-8')
            : ucfirst($type);
    };
    $grouped = [];
    foreach ($terms as $term) {
        $grouped[(string)$term['type']][] = $term;
    }
?>
  <div class="card">
    <h2 style="margin-top:0">Štítky a kategorie</h2>

    <?php if ($availableTypes): ?>
      <nav class="term-types">
        <a class="term-types__link<?= $activeType === null ? ' term-types__link--active' : '' ?>" href="./terms">Vše</a>
        <?php foreach ($availableTypes as $type): ?>
          <a class="term-types__link<?= $activeType === $type ? ' term-types__link--active' : '' ?>" href="./terms/<?= $h($type) ?>">
            <?= $h($formatType($type)) ?>
          </a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>

    <?php if (!$terms): ?>
      <p class="meta">Žádné termy k zobrazení.</p>
    <?php else: ?>
      <?php foreach ($grouped as $type => $items): ?>
        <section class="term-group">
          <h3 class="term-group__title"><?= $h($formatType($type)) ?></h3>
          <ul class="term-list">
            <?php foreach ($items as $term): ?>
              <?php $count = (int)($term['posts_count'] ?? 0); ?>
              <li class="term-list__item">
                <div class="term-list__header">
                  <a class="term-list__name" href="./term/<?= $h((string)$term['slug']) ?>"><?= $h((string)$term['name']) ?></a>
                  <span class="term-list__count"><?= $count ?> <?= $count === 1 ? 'příspěvek' : 'příspěvků' ?></span>
                </div>
                <?php if (!empty($term['description'])): ?>
                  <p class="term-list__description"><?= $h((string)$term['description']) ?></p>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
<?php }); ?>
