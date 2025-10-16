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
/** @var array{date:array<int,string>,time:array<int,string>,datetime:array<int,string>} $formatPresets */

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($settings,$csrf,$timezones,$previewNow,$formatPresets) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $sel = fn(string $a,string $b)=> $a===$b?' selected':'';
  $datePresets = is_array($formatPresets['date'] ?? null) ? $formatPresets['date'] : [];
  $timePresets = is_array($formatPresets['time'] ?? null) ? $formatPresets['time'] : [];
  $datetimePresets = is_array($formatPresets['datetime'] ?? null) ? $formatPresets['datetime'] : [];
  $webpEnabled = (int)($settings['webp_enabled'] ?? 0) === 1;
  $webpCompression = (string)($settings['webp_compression'] ?? 'medium');
?>
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

      <div class="row g-3 mt-3">
        <div class="col-md-6">
          <label class="form-label">Podpora WebP</label>
          <select class="form-select" name="webp_enabled" id="webp_enabled">
            <option value="0"<?= $webpEnabled ? '' : ' selected' ?>>Vypnuto</option>
            <option value="1"<?= $webpEnabled ? ' selected' : '' ?>>Zapnuto</option>
          </select>
          <div class="form-text">Při zapnutí se budou z nahraných obrázků vytvářet také WebP soubory (pokud to server umožní).</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Komprese WebP</label>
          <select class="form-select" name="webp_compression" id="webp_compression"<?= $webpEnabled ? '' : ' disabled' ?>>
            <option value="high"<?= $webpCompression==='high'?' selected':''; ?>>Velká komprese</option>
            <option value="medium"<?= $webpCompression==='medium'?' selected':''; ?>>Střední komprese</option>
            <option value="low"<?= $webpCompression==='low'?' selected':''; ?>>Lehká komprese</option>
          </select>
          <div class="form-text">Vyšší komprese znamená menší soubory, ale také nižší kvalitu.</div>
        </div>
      </div>

      <hr>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Formát data</label>
          <?php $curDateFmt = (string)($settings['date_format'] ?? 'Y-m-d'); ?>
          <select class="form-select" name="date_format" id="date_format">
            <?php foreach ($datePresets as $preset): ?>
              <?php if (!is_string($preset)) continue; ?>
              <option value="<?= $h($preset) ?>"<?= $sel($preset, $curDateFmt) ?>><?= $h($preset) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Vyber z nabídky předpřipravených PHP formátů <code>date()</code>.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Formát času</label>
          <?php $curTimeFmt = (string)($settings['time_format'] ?? 'H:i'); ?>
          <select class="form-select" name="time_format" id="time_format">
            <?php foreach ($timePresets as $preset): ?>
              <?php if (!is_string($preset)) continue; ?>
              <option value="<?= $h($preset) ?>"<?= $sel($preset, $curTimeFmt) ?>><?= $h($preset) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Vyber z nabídky předpřipravených PHP formátů <code>date()</code>.</div>
          <input class="form-control" name="date_format" id="date_format" value="<?= $h((string)($settings['date_format'] ?? 'Y-m-d')) ?>" placeholder="např. Y-m-d" list="dateFormatPresets">
          <datalist id="dateFormatPresets">
            <?php foreach ($datePresets as $preset): ?>
              <option value="<?= $h((string)$preset) ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <div class="form-text">Vyber z nabídky nebo napiš vlastní dle PHP <code>date()</code>.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Formát času</label>
          <input class="form-control" name="time_format" id="time_format" value="<?= $h((string)($settings['time_format'] ?? 'H:i')) ?>" placeholder="např. H:i" list="timeFormatPresets">
          <datalist id="timeFormatPresets">
            <?php foreach ($timePresets as $preset): ?>
              <option value="<?= $h((string)$preset) ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <div class="form-text">Vyber z nabídky nebo napiš vlastní dle PHP <code>date()</code>.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Časová zóna</label>
          <select class="form-select" name="timezone" id="timezone">
            <?php $curTz = (string)($settings['timezone'] ?? 'Europe/Prague'); ?>
            <?php foreach ($timezones as $tz): ?>
              <?php if (!is_string($tz)) continue; ?>
              <option value="<?= $h($tz) ?>"<?= $sel($tz,$curTz) ?>><?= $h($tz) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Použije se pro zobrazení času napříč webem.</div>
        </div>
      </div>

      <?php if ($datetimePresets): ?>
        <div class="form-text mt-2">
          Oblíbené kombinace: <?php foreach ($datetimePresets as $idx => $preset): ?><?= $idx ? ', ' : '' ?><code><?= $h((string)$preset) ?></code><?php endforeach; ?>
        </div>
      <?php endif; ?>

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
      const webpToggle = document.getElementById('webp_enabled');
      const webpCompression = document.getElementById('webp_compression');

      function updatePreview() {
        if (!dateI || !timeI || !prev) return;
        prev.textContent = dateI.value + ' ' + timeI.value + ' — např. <?= $h($previewNow) ?>';
      }

      function updateWebpState() {
        if (!webpToggle || !webpCompression) return;
        webpCompression.disabled = webpToggle.value !== '1';
      }

      if (dateI) dateI.addEventListener('change', updatePreview);
      if (timeI) timeI.addEventListener('change', updatePreview);
      if (dateI) dateI.addEventListener('input', updatePreview);
      if (timeI) timeI.addEventListener('input', updatePreview);
      if (webpToggle) webpToggle.addEventListener('change', updateWebpState);

      updatePreview();
      updateWebpState();
    })();
  </script>
<?php
});
