<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array $mail */
/** @var array<string,string> $drivers */
/** @var string $csrf */
/** @var string $siteEmail */
/** @var string $siteName */

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($mail,$drivers,$csrf,$siteEmail,$siteName) {
    $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $driver = is_string($mail['driver'] ?? null) ? (string)$mail['driver'] : 'php';
?>
  <form class="card" method="post" action="admin.php?r=settings&a=mail" id="mailSettingsForm" data-ajax data-action="settings_mail_save">
    <div class="card-body">
      <div class="mb-4">
        <h2 class="h6 text-uppercase text-secondary fw-semibold mb-3">Způsob odesílání</h2>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="mail_driver">Vybraný mailer</label>
            <select class="form-select" name="mail_driver" id="mail_driver">
              <?php foreach ($drivers as $value => $label): ?>
                <option value="<?= $h((string)$value) ?>"<?= $driver === $value ? ' selected' : '' ?>><?= $h($label) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Vyberte způsob, jakým bude systém odesílat e-maily.</div>
          </div>
        </div>
      </div>

      <div class="mb-4 border-top pt-4">
        <h2 class="h6 text-uppercase text-secondary fw-semibold mb-3">Odesílatel</h2>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="mail_from_email">E-mail odesílatele</label>
            <input class="form-control" type="email" id="mail_from_email" name="mail_from_email" value="<?= $h((string)($mail['from_email'] ?? '')) ?>" placeholder="např. no-reply@example.com">
            <div class="form-text">Pokud pole necháte prázdné, použije se e-mail webu (<?= $h($siteEmail ?: 'nenastaveno') ?>).</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="mail_from_name">Jméno odesílatele</label>
            <input class="form-control" id="mail_from_name" name="mail_from_name" value="<?= $h((string)($mail['from_name'] ?? '')) ?>" placeholder="<?= $h($siteName) ?>">
            <div class="form-text">Necháte-li prázdné, použije se název webu.</div>
          </div>
        </div>
      </div>

      <div class="mb-4 border-top pt-4" id="smtpSection">
        <h2 class="h6 text-uppercase text-secondary fw-semibold mb-3">SMTP server</h2>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="mail_smtp_host">Host</label>
            <input class="form-control" id="mail_smtp_host" name="mail_smtp_host" value="<?= $h((string)($mail['smtp_host'] ?? '')) ?>" placeholder="smtp.example.com">
          </div>
          <div class="col-md-3">
            <label class="form-label" for="mail_smtp_port">Port</label>
            <input class="form-control" type="number" id="mail_smtp_port" name="mail_smtp_port" value="<?= $h((string)($mail['smtp_port'] ?? '587')) ?>" min="1" max="65535">
          </div>
          <div class="col-md-3">
            <label class="form-label" for="mail_smtp_secure">Zabezpečení</label>
            <?php $secure = (string)($mail['smtp_secure'] ?? ''); ?>
            <select class="form-select" id="mail_smtp_secure" name="mail_smtp_secure">
              <option value=""<?= $secure === '' ? ' selected' : '' ?>>Žádné</option>
              <option value="tls"<?= $secure === 'tls' ? ' selected' : '' ?>>TLS</option>
              <option value="ssl"<?= $secure === 'ssl' ? ' selected' : '' ?>>SSL</option>
            </select>
          </div>
        </div>
        <div class="row g-3 mt-1">
          <div class="col-md-6">
            <label class="form-label" for="mail_smtp_username">Uživatelské jméno</label>
            <input class="form-control" id="mail_smtp_username" name="mail_smtp_username" value="<?= $h((string)($mail['smtp_username'] ?? '')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="mail_smtp_password">Heslo</label>
            <input class="form-control" type="password" id="mail_smtp_password" name="mail_smtp_password" value="<?= $h((string)($mail['smtp_password'] ?? '')) ?>">
          </div>
        </div>
        <div class="form-text mt-2">Vyplňte pouze pokud používáte SMTP. Údaje poskytuje váš poskytovatel e-mailu.</div>
      </div>

      <div class="mb-4 border-top pt-4">
        <h2 class="h6 text-uppercase text-secondary fw-semibold mb-3">Podpis</h2>
        <label class="form-label" for="mail_signature">Text podpisu</label>
        <textarea class="form-control" id="mail_signature" name="mail_signature" rows="4" placeholder="Např. Tým <?= $h($siteName) ?>"><?= $h((string)($mail['signature'] ?? '')) ?></textarea>
        <div class="form-text">Podpis se automaticky připojí na konec každého e-mailu. Podporuje běžný text, řádky se převedou na odstavce.</div>
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

  <form class="card mt-4" method="post" action="admin.php?r=settings&a=mail" data-ajax data-action="settings_mail_test">
    <div class="card-body">
      <h2 class="h6 text-uppercase text-secondary fw-semibold mb-3">Testovací e-mail</h2>
      <p class="text-secondary small">Odešle jednoduchý testovací e-mail pomocí aktuálně uložené konfigurace.</p>
      <div class="row g-3 align-items-end">
        <div class="col-md-8 col-lg-6">
          <label class="form-label" for="test_email">Adresát</label>
          <input class="form-control" type="email" id="test_email" name="test_email" placeholder="např. user@example.com" required>
        </div>
        <div class="col-md-4 col-lg-3">
          <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
          <input type="hidden" name="intent" value="test">
          <button class="btn btn-outline-primary w-100" type="submit">
            <i class="bi bi-send me-1"></i>Odeslat test
          </button>
        </div>
      </div>
    </div>
  </form>

  <script>
    (function(){
      const driver = document.getElementById('mail_driver');
      const smtpSection = document.getElementById('smtpSection');
      if (!driver || !smtpSection) return;
      function toggle(){
        const isSmtp = driver.value === 'smtp';
        smtpSection.classList.toggle('d-none', !isSmtp);
      }
      driver.addEventListener('change', toggle);
      toggle();
    })();
  </script>
<?php
});
