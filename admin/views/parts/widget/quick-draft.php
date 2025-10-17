<?php
/** @var string $csrf */
/** @var array<int,array{value:string,label:string}> $types */
/** @var array<string,string> $values */
$h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$currentType = $values['type'] ?? 'post';
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
        <label class="form-label" for="quick-draft-type">Typ obsahu</label>
        <select class="form-select form-select-sm" id="quick-draft-type" name="type">
          <?php foreach ($types as $type): ?>
            <option value="<?= $h($type['value']) ?>" <?= $currentType === $type['value'] ? 'selected' : '' ?>><?= $h($type['label']) ?></option>
          <?php endforeach; ?>
        </select>
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
</div>
