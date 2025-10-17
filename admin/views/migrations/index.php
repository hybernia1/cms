<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array<int,\Core\Database\Migrations\Migration> $all */
/** @var array<string,bool> $applied */
/** @var string $csrf */

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($all,$applied,$csrf) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
  <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center gap-2 mb-3">
    <form method="post" action="admin.php?r=migrations&a=run" class="order-1" data-ajax>
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <button class="btn btn-success btn-sm" type="submit">
        <i class="bi bi-play-circle me-1"></i>Spustit čekající migrace
      </button>
    </form>
    <form method="post" action="admin.php?r=migrations&a=rollback" class="order-2" onsubmit="return confirm('Rollback posledního batchu – pokračovat?');" data-ajax>
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <button class="btn btn-outline-warning btn-sm" type="submit">
        <i class="bi bi-arrow-counterclockwise me-1"></i>Rollback posledního batchu
      </button>
    </form>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Soubor / Třída</th><th style="width:180px">Stav</th></tr></thead>
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
                  <span class="badge text-bg-success-subtle text-success-emphasis border border-success-subtle">Aplikováno</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle">Čeká</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$all): ?>
            <tr><td colspan="2" class="text-center text-secondary py-4"><i class="bi bi-inbox me-1"></i>Žádné migrace nenalezeny</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php
});
