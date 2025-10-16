<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */

// pro render použijeme layout s obsahem přes slot $content()
$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () {
?>
  <div class="card">
    <div class="card-body">
      <h1 class="h4 mb-3">Vítej v administraci</h1>
      <p class="text-secondary mb-3">Tady budeme postupně přidávat přehledy (počty příspěvků, poslední komentáře apod.).</p>

      <div class="row g-3">
        <div class="col-md-4">
          <div class="card">
            <div class="card-body">
              <div class="text-secondary">Příspěvky</div>
              <div class="fs-4 fw-semibold">–</div>
              <div class="small text-secondary">Přehled doplníme v Kroku 5.</div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card">
            <div class="card-body">
              <div class="text-secondary">Komentáře</div>
              <div class="fs-4 fw-semibold">–</div>
              <div class="small text-secondary">Moderace v Kroku 8.</div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card">
            <div class="card-body">
              <div class="text-secondary">Média</div>
              <div class="fs-4 fw-semibold">–</div>
              <div class="small text-secondary">Knihovna v Kroku 6.</div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
<?php
});
