<?php
/** @var array<int,array<string,mixed>> $terms */
/** @var string|null $activeType */
/** @var array<int,string> $availableTypes */
/** @var \Cms\Utils\LinkGenerator $urls */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$grouped = [];
foreach ($terms as $term) {
    $group = (string)($term['type'] ?? 'ostatní');
    $grouped[$group][] = $term;
}
ksort($grouped);
?>
<section class="card card--section">
  <header class="card__header card__header--stacked">
    <h1 class="card__title">Termy</h1>
    <?php if ($availableTypes): ?>
      <nav class="pill-nav" aria-label="Typ termu">
        <a class="pill-nav__link<?= $activeType === null ? ' pill-nav__link--active' : '' ?>" href="<?= $h($urls->terms()) ?>">Vše</a>
        <?php foreach ($availableTypes as $type): ?>
          <a
            class="pill-nav__link<?= $activeType === $type ? ' pill-nav__link--active' : '' ?>"
            href="<?= $h($urls->terms($type)) ?>"
          ><?= $h(ucfirst($type)) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
  </header>

  <?php if (!$grouped): ?>
    <p class="muted">Žádné termy zatím nejsou vytvořeny.</p>
  <?php else: ?>
    <?php foreach ($grouped as $type => $items): ?>
      <?php if ($activeType !== null && $activeType !== $type) { continue; } ?>
      <section class="term-group">
        <h2 class="term-group__title"><?= $h(ucfirst($type)) ?></h2>
        <ul class="term-grid">
          <?php foreach ($items as $item): ?>
            <li class="term-card">
              <a class="term-card__title" href="<?= $h($urls->term((string)$item['slug'], (string)$item['type'])) ?>">
                <?= $h((string)($item['name'] ?? 'Bez názvu')) ?>
              </a>
              <?php if (!empty($item['description'])): ?>
                <p class="term-card__description"><?= $h((string)$item['description']) ?></p>
              <?php endif; ?>
              <span class="term-card__meta">Příspěvků: <?= (int)($item['posts_count'] ?? 0) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
