<?php
/** @var \Cms\View\Assets $assets */
/** @var string $siteTitle */
/** @var array<int,array> $items */
/** @var string|null $type */
/** @var array{id:mixed,name:string,slug:string,type:string}|null $term */
/** @var \Cms\Utils\LinkGenerator $urls */
$this->render('layouts/base', compact('assets', 'siteTitle'), function() use ($items, $type, $urls, $term) {
    $formatTermHeading = static function(?array $term, ?string $fallback): string {
        if ($term) {
            $type = (string)($term['type'] ?? '');
            $label = match ($type) {
                'category' => 'Kategorie',
                'tag'      => 'Štítek',
                default    => ucfirst($type !== '' ? $type : 'Term'),
            };
            return $label . ': ' . (string)($term['name'] ?? '');
        }
        return $fallback ? (string)$fallback : '';
    };
    $heading = $formatTermHeading($term, $type);
?>
  <div class="card">
    <h2 style="margin-top:0">Archiv<?= $heading !== '' ? ' – ' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') : '' ?></h2>
    <?php if (!$items): ?>
      <p class="meta">Nic k zobrazení.</p>
    <?php else: ?>
      <ul>
        <?php foreach ($items as $p): ?>
          <li>
            <a href="<?= htmlspecialchars($urls->post((string)$p['slug']), ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars((string)$p['title'], ENT_QUOTES, 'UTF-8') ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
<?php }); ?>
