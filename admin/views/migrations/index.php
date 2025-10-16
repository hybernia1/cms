<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array<int,\Core\Database\Migrations\Migration> $all */
/** @var array<string,bool> $applied */
/** @var string $csrf */

$this->render('layouts/base', compact('pageTitle','nav','currentUser'), function () use ($flash,$all,$applied,$csrf) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
  <?php if ($flash): ?>
    <div class="alert alert-<?= $h((string)$flash['type']) ?>"><?= $h((string)$flash['msg']) ?></div>
  <?php endif; ?>

  <div class="d-flex gap-2 mb-3">
    <form method="post" action="admin.php?r=migrations&a=run">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <button class="btn btn-primary" type="submit">Spustit čekající migrace</button>
    </form>
    <form method="post" action="admin.php?r=migrations&a=rollback" onsubmit="return confirm('Rollback posledního batchu – pokračovat?');">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <button class="btn btn-outline-warning" type="submit">Rollback posledního batchu</button>
    </form>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-dark table-hover mb-0">
        <thead><tr><th>Soubor / Třída</th><th>Stav</th></tr></thead>
        <tbody>
          <?php foreach ($all as $m):
                $rf = new ReflectionClass($m);
                $file = basename((string)$rf->getFileName());
                $name = $m->name();
                $isApplied = isset($applied[$name]);
          ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= $h($name) ?></div>
                <div class="small text-secondary"><?= $h($rf->getName()) ?> • <?= $h($file) ?></div>
              </td>
              <td>
                <?php if ($isApplied): ?>
                  <span class="badge text-bg-success">APLIKOVÁNO</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary">PENDING</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$all): ?>
            <tr><td colspan="2" class="text-center text-secondary py-4">Žádné migrace nenalezeny</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php
});
