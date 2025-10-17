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
  $sel = fn(string $a,string $b): string => $a===$b?' selected':'';
  $datePresets = is_array($formatPresets['date'] ?? null) ? $formatPresets['date'] : [];
  $timePresets = is_array($formatPresets['time'] ?? null) ? $formatPresets['time'] : [];
  $datetimePresets = is_array($formatPresets['datetime'] ?? null) ? $formatPresets['datetime'] : [];
  $webpEnabled = (int)($settings['webp_enabled'] ?? 0) === 1;
  $webpCompression = (string)($settings['webp_compression'] ?? 'medium');
  $curTz = \Cms\Utils\SettingsPresets::normalizeTimezone((string)($settings['timezone'] ?? 'UTC+01:00'));
  $tzLabel = static function(string $tz): string {
    return \Cms\Utils\SettingsPresets::timezoneLabel($tz);
  };
?>
  <form class="card" method="post" action="admin.php?r=settings&a=index" id="settingsForm" data-ajax>
    <div class="card-body">
      <div class="mb-4">
        <h2 class="h6 text-uppercase text-secondary fw-semibold mb-3">Základní informace</h2>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="site_title">Název webu</label>
            <input class="form-control" id="site_title" name="site_title" value="<?= $h((string)($settings['site_title'] ?? '')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="site_email">E-mail webu</label>
            <input class="form-control" id="site_email" name="site_email" value="<?= $h((string)($settings['site_email'] ?? '')) ?>" type="email" placeholder="admin@example.com">
          </div>
        </div>
      </div>

      <div class="mb-4 border-top pt-4">
        <h2 class="h6 text-uppercase text-secondary fw-semibold mb-3">Registrace &amp; URL</h2>
        <div class="row g-3">
          <div class="col-md-6">
            <?php $ar = (int)($settings['allow_registration'] ?? 1); ?>
            <input type="hidden" name="allow_registration" value="0">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="allow_registration" name="allow_registration" value="1" <?= $ar===1 ? 'checked' : '' ?>>
              <label class="form-check-label" for="allow_registration">Veřejná registrace</label>
            </div>
            <div class="form-text">Noví uživatelé se mohou registrovat přes frontend formulář.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="site_url">Site URL</label>
            <input class="form-control" id="site_url" name="site_url" value="<?= $h((string)($settings['site_url'] ?? '')) ?>" placeholder="https://example.com">
            <div class="form-text">Nechte prázdné pro automatickou detekci.</div>
          </div>
        </div>
      </div>

      <div class="mb-4 border-top pt-4">
        <h2 class="h6 text-uppercase text-secondary fw-semibold mb-3">Média</h2>
        <div class="row g-3">
          <div class="col-md-6">
            <input type="hidden" name="webp_enabled" value="0">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="webp_enabled" name="webp_enabled" value="1" <?= $webpEnabled ? 'checked' : '' ?>>
              <label class="form-check-label" for="webp_enabled">Podpora WebP</label>
            </div>
            <div class="form-text">Při zapnutí se budou z nahraných obrázků vytvářet také WebP soubory (pokud to server umožní).</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="webp_compression">Komprese WebP</label>
            <select class="form-select" name="webp_compression" id="webp_compression"<?= $webpEnabled ? '' : ' disabled' ?>>
              <option value="high"<?= $webpCompression==='high'?' selected':''; ?>>Velká komprese</option>
              <option value="medium"<?= $webpCompression==='medium'?' selected':''; ?>>Střední komprese</option>
              <option value="low"<?= $webpCompression==='low'?' selected':''; ?>>Lehká komprese</option>
            </select>
            <div class="form-text">Vyšší komprese znamená menší soubory, ale také nižší kvalitu.</div>
          </div>
        </div>
      </div>

      <div class="mb-4 border-top pt-4">
        <h2 class="h6 text-uppercase text-secondary fw-semibold mb-3">Datum &amp; čas</h2>
        <div class="row g-3 align-items-end">
          <div class="col-lg-4">
            <label class="form-label" for="date_format">Formát data</label>
            <input class="form-control" name="date_format" id="date_format" value="<?= $h((string)($settings['date_format'] ?? 'Y-m-d')) ?>" placeholder="např. Y-m-d" list="dateFormatPresets">
            <div class="form-text">Použijte PHP zápis pro <code>date()</code>.</div>
          </div>
          <div class="col-lg-4">
            <label class="form-label" for="time_format">Formát času</label>
            <input class="form-control" name="time_format" id="time_format" value="<?= $h((string)($settings['time_format'] ?? 'H:i')) ?>" placeholder="např. H:i" list="timeFormatPresets">
            <div class="form-text">Použijte PHP zápis pro <code>date()</code>.</div>
          </div>
          <div class="col-lg-4">
            <label class="form-label" for="timezone">Časová zóna</label>
            <select class="form-select" name="timezone" id="timezone">
              <?php foreach ($timezones as $tz): ?>
                <?php if (!is_string($tz)) continue; ?>
                <option value="<?= $h($tz) ?>"<?= $sel($tz,$curTz) ?>><?= $h($tzLabel($tz)) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Vyberte požadovaný posun vůči UTC.</div>
          </div>
        </div>
        <datalist id="dateFormatPresets">
          <?php foreach ($datePresets as $preset): ?>
            <?php if (!is_string($preset)) continue; ?>
            <option value="<?= $h($preset) ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <datalist id="timeFormatPresets">
          <?php foreach ($timePresets as $preset): ?>
            <?php if (!is_string($preset)) continue; ?>
            <option value="<?= $h($preset) ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <?php if ($datetimePresets): ?>
          <div class="form-text mt-2">
            Oblíbené kombinace:
            <?php foreach ($datetimePresets as $idx => $preset): ?>
              <?php if (!is_string($preset)) continue; ?>
              <code class="me-1"><?= $h($preset) ?></code><?= $idx < count($datetimePresets)-1 ? ',' : '' ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="alert alert-secondary mt-3 mb-0">
          <div class="d-flex align-items-center gap-2">
            <strong>Náhled:</strong>
            <span id="preview"><?= $h($previewNow) ?></span>
          </div>
        </div>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <span class="text-secondary small">Změny se projeví po uložení.</span>
      <button class="btn btn-primary" type="submit">
        <i class="bi bi-check2-circle me-1"></i>Uložit nastavení
      </button>
    </div>
  </form>

  <script>
    (function(){
      const dateInput = document.getElementById('date_format');
      const timeInput = document.getElementById('time_format');
      const previewEl = document.getElementById('preview');
      const webpToggle = document.getElementById('webp_enabled');
      const webpCompression = document.getElementById('webp_compression');
      const timezoneSelect = document.getElementById('timezone');

      function updatePreview() {
        if (!dateInput || !timeInput || !previewEl) return;
        const dateVal = dateInput.value || 'Y-m-d';
        const timeVal = timeInput.value || 'H:i';
        let tzPreview = '';
        if (timezoneSelect && timezoneSelect.selectedIndex >= 0) {
          tzPreview = timezoneSelect.options[timezoneSelect.selectedIndex].textContent || '';
          tzPreview = tzPreview.trim();
        }
        previewEl.textContent = dateVal + ' ' + timeVal + (tzPreview ? ' (' + tzPreview + ')' : '') + ' — např. <?= $h($previewNow) ?>';
      }

      function updateWebpState() {
        if (!webpToggle || !webpCompression) return;
        webpCompression.disabled = !webpToggle.checked;
      }

      if (dateInput) {
        dateInput.addEventListener('input', updatePreview);
        dateInput.addEventListener('change', updatePreview);
      }
      if (timeInput) {
        timeInput.addEventListener('input', updatePreview);
        timeInput.addEventListener('change', updatePreview);
      }
      if (webpToggle) {
        webpToggle.addEventListener('change', updateWebpState);
      }
      if (timezoneSelect) {
        timezoneSelect.addEventListener('change', updatePreview);
      }

      updatePreview();
      updateWebpState();
    })();
  </script>
<?php
});
