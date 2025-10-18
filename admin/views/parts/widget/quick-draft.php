<?php
/** @var string $csrf */
/** @var array<string,string> $values */
/** @var array<int,array{id:int,title:string,type:string,created_at_display:string}> $recentDrafts */
$h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$currentType = 'post';
$titleValue = $values['title'] ?? '';
$contentValue = $values['content'] ?? '';
?>
<div class="card shadow-sm h-100">
  <div class="card-header fw-semibold">Rychlý koncept</div>
  <form method="post" action="admin.php?r=dashboard&a=quick-draft" data-ajax>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label" for="quick-draft-title">Titulek</label>
        <input class="form-control form-control-sm" id="quick-draft-title" name="title" placeholder="Název konceptu" value="<?= $h($titleValue) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Typ obsahu</label>
        <div class="form-control-plaintext form-control-sm text-secondary">Příspěvek</div>
        <input type="hidden" name="type" value="post">
      </div>
      <div class="mb-3">
        <label class="form-label" for="quick-draft-content">Obsah</label>
        <textarea class="form-control form-control-sm" id="quick-draft-content" name="content" rows="5" placeholder="Poznámky nebo úvod…"><?= $h($contentValue) ?></textarea>
      </div>
      <p class="text-secondary small mb-0">Koncept bude uložen jako <strong>nepublikovaný</strong> a můžete se k němu vrátit později.</p>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center gap-2">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <button class="btn btn-primary btn-sm" type="submit">Uložit koncept</button>
      <a class="btn btn-outline-secondary btn-sm" href="admin.php?r=posts&type=<?= $h($currentType) ?>" data-no-ajax>Otevřít přehled</a>
    </div>
  </form>
  <div class="card-body border-top bg-body-tertiary">
    <div class="small fw-semibold mb-2">Poslední koncepty</div>
    <?php if ($recentDrafts): ?>
      <ul class="list-unstyled mb-0 small">
        <?php foreach ($recentDrafts as $draft): ?>
          <?php
            $title = trim((string)($draft['title'] ?? ''));
            if ($title === '') {
                $title = 'Bez názvu';
            }
          ?>
          <li class="mb-1">
            <a class="link-body-emphasis" href="admin.php?r=posts&a=edit&id=<?= (int)$draft['id'] ?>&type=<?= $h((string)($draft['type'] ?? 'post')) ?>" data-no-ajax>
              <?= $h($title) ?>
            </a>
            <?php if (($draft['created_at_display'] ?? '') !== ''): ?>
              <div class="text-secondary"><?= $h((string)$draft['created_at_display']) ?></div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="text-secondary small mb-0">Zatím žádné koncepty.</p>
    <?php endif; ?>
  </div>
</div>
