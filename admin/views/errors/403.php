<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */

$this->render('layouts/base', compact('pageTitle','nav','currentUser'), function () {
?>
  <div class="card">
    <div class="card-body">
      <h1 class="h5">403 – Přístup odepřen</h1>
      <p class="text-secondary">Nemáte oprávnění vstoupit do administrace.</p>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="./">Zpět na web</a>
        <a class="btn btn-primary" href="admin.php?r=auth&a=logout">Odhlásit a přihlásit jinak</a>
      </div>
    </div>
  </div>
<?php
});
