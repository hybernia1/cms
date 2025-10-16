<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array $settings */
/** @var string $csrf */
/** @var array<int,string> $timezones */
/** @var string $previewNow */

$this->render('layouts/base', compact('pageTitle','nav','currentUser'), function () use ($flash,$settings,$csrf,$timezones,$previewNow) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $sel = fn(string $a,string $b)=> $a===$b?' selected':'';
?>
  <?php if ($flash): ?>
    <div class="alert alert-<?= $h((string)$flash['type']) ?>"><?= $h((string)$flash['msg']) ?></div>
  <?php endif; ?>

  <form class="card" method="post" action="admin.php?r=settings&a=index" id="settingsForm">
    <div class="card-header">Základní nastavení</div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">Název webu</label>
        <input class="form-control" name="site_title" value="<?= $h((string)($settings['site_title'] ?? '')) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">E-mail webu</label>
        <input class="form-control" name="site_email" value="<?= $h((string)($settings['site_email'] ?? '')) ?>">
      </div>

      <hr>

    <hr>

<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label">Veřejná registrace</label>
    <?php $ar = (int)($settings['allow_registration'] ?? 1); ?>
    <select class="form-select" name="allow_registration">
      <option value="1"<?= $ar===1?' selected':''; ?>>Povoleno</option>
      <option value="0"<?= $ar===0?' selected':''; ?>>Zakázáno</option>
    </select>
  </div>
  <div class="col-md-6">
    <label class="form-label">Site URL</label>
    <input class="form-control" name="site_url" value="<?= $h((string)($settings['site_url'] ?? '')) ?>" placeholder="https://example.com">
    <div class="form-text">Nechte prázdné pro automatickou detekci.</div>
  </div>
</div>


      <hr>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Formát data</label>
          <input class="form-control" name="date_format" id="date_format" value="<?= $h((string)($settings['date_format'] ?? 'Y-m-d')) ?>" placeholder="např. Y-m-d">
          <div class="form-text">Příklady: <code>Y-m-d</code>, <code>d.m.Y</code>, <code>j. n. Y</code></div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Formát času</label>
          <input class="form-control" name="time_format" id="time_format" value="<?= $h((string)($settings['time_format'] ?? 'H:i')) ?>" placeholder="např. H:i">
          <div class="form-text">Příklady: <code>H:i</code>, <code>H:i:s</code>, <code>g:i A</code></div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Časová zóna</label>
          <select class="form-select" name="timezone" id="timezone">
            <?php $curTz = (string)($settings['timezone'] ?? 'Europe/Prague'); ?>
            <?php foreach ($timezones as $tz): ?>
              <option value="<?= $h($tz) ?>"<?= $sel($tz,$curTz) ?>><?= $h($tz) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Použije se pro zobrazení času napříč webem.</div>
        </div>
      </div>

      <div class="alert alert-secondary mt-3 mb-0">
        <div class="d-flex align-items-center gap-2">
          <strong>Náhled:</strong>
          <span id="preview"><?= $h($previewNow) ?></span>
        </div>
      </div>
    </div>
    <div class="card-footer">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <button class="btn btn-primary" type="submit">Uložit</button>
    </div>
  </form>

  <script>
    (function(){
      // nejde věrně formátovat v JS dle PHP tokenů, ale jako preview použijeme serverem vyrenderované "teď"
      // a budeme pouze měnit oddělovače heuristicky – pro přesnost by bylo potřeba volat server (AJAX).
      // Uděláme jednoduchý náhled: zobrazíme hodnoty formátů a zdůrazníme, že je to ilustrační.
      const dateI = document.getElementById('date_format');
      const timeI = document.getElementById('time_format');
      const prev  = document.getElementById('preview');

      function updatePreview() {
        // Minimálně zobraz: "{date_format} {time_format} (např. <?= $h($previewNow) ?>)"
        prev.textContent = dateI.value + ' ' + timeI.value + ' — např. <?= $h($previewNow) ?>';
      }
      dateI.addEventListener('input', updatePreview);
      timeI.addEventListener('input', updatePreview);
    })();
  </script>
<?php
});
