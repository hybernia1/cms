<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array<int,array<string,mixed>> $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var int $confirmedCount */
/** @var string $csrf */

$this->render('parts/layouts/base', compact('pageTitle', 'nav', 'currentUser', 'flash'), function () use ($confirmedCount, $csrf) {
    $h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
  <div class="card">
    <div class="card-body">
      <p class="mb-3">
        Export obsahuje pouze adresy ve stavu <strong>Potvrzeno</strong>. Soubor je generován ve formátu CSV se středníkem jako oddělovačem.
      </p>
      <ul class="mb-4">
        <li>Počet potvrzených adres: <strong><?= (int)$confirmedCount ?></strong></li>
        <li>Sloupce: <code>email</code>, <code>confirmed_at</code>, <code>created_at</code>, <code>source_url</code></li>
      </ul>
      <?php if ($confirmedCount === 0): ?>
        <div class="alert alert-warning" role="alert">Zatím nejsou žádné potvrzené adresy k exportu.</div>
      <?php endif; ?>
    </div>
    <div class="card-footer d-flex justify-content-between">
      <a class="btn btn-outline-secondary" href="admin.php?r=newsletter">&larr; Zpět na seznam</a>
      <form method="post" action="admin.php?r=newsletter&a=export-confirmed">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <button class="btn btn-primary" type="submit"<?= $confirmedCount === 0 ? ' disabled' : '' ?>>Stáhnout CSV</button>
      </form>
    </div>
  </div>
<?php
});
