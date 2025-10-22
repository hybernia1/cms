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

$this->render('parts/layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($mail,$drivers,$csrf,$siteEmail,$siteName) {
    $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $driver = is_string($mail['driver'] ?? null) ? (string)$mail['driver'] : 'php';
?>
  <form class="card" method="post" action="admin.php?r=settings&a=mail" id="mailSettingsForm" data-ajax data-form-helper="validation">
    <div class="card-body">
      <div class="alert alert-danger mb-3" data-error-for="form" hidden></div>
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
            <div class="invalid-feedback" data-error-for="mail_from_email" hidden></div>
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
            <div class="invalid-feedback" data-error-for="mail_smtp_host" hidden></div>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="mail_smtp_port">Port</label>
            <input class="form-control" type="number" id="mail_smtp_port" name="mail_smtp_port" value="<?= $h((string)($mail['smtp_port'] ?? '587')) ?>" min="1" max="65535">
            <div class="invalid-feedback" data-error-for="mail_smtp_port" hidden></div>
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

  <form class="card mt-4" method="post" action="admin.php?r=settings&a=mail" data-ajax data-form-helper="validation" id="mailTestForm">
    <div class="card-body">
      <h2 class="h6 text-uppercase text-secondary fw-semibold mb-3">Testovací e-mail</h2>
      <p class="text-secondary small">Odešle jednoduchý testovací e-mail pomocí aktuálně uložené konfigurace.</p>
      <div class="alert alert-danger mb-3" data-error-for="form" hidden></div>
      <div class="row g-3 align-items-end">
        <div class="col-md-8 col-lg-6">
          <label class="form-label" for="test_email">Adresát</label>
          <input class="form-control" type="email" id="test_email" name="test_email" placeholder="např. user@example.com" required>
          <div class="invalid-feedback" data-error-for="test_email" hidden></div>
        </div>
        <div class="col-md-4 col-lg-3">
          <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
          <input type="hidden" name="intent" value="test">
          <button class="btn btn-outline-primary w-100" type="submit" data-submit-button>
            <span class="spinner-border spinner-border-sm me-2 d-none" data-spinner role="status" aria-hidden="true"></span>
            <i class="bi bi-send me-1" data-icon aria-hidden="true"></i>
            <span data-label>Odeslat test</span>
          </button>
        </div>
      </div>
      <div class="mt-3 alert d-none" data-test-mail-result hidden></div>
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

      const testForm = document.getElementById('mailTestForm');
      if (testForm) {
        const submitBtn = testForm.querySelector('[data-submit-button]');
        const spinner = submitBtn ? submitBtn.querySelector('[data-spinner]') : null;
        const icon = submitBtn ? submitBtn.querySelector('[data-icon]') : null;
        const resultBox = testForm.querySelector('[data-test-mail-result]');

        const setLoading = (state) => {
          if (spinner) {
            spinner.classList.toggle('d-none', !state);
          }
          if (icon) {
            icon.classList.toggle('d-none', state);
          }
        };

        const showResult = (type, message) => {
          if (!resultBox) {
            return;
          }
          const variants = {
            success: 'alert-success',
            danger: 'alert-danger',
            warning: 'alert-warning',
            info: 'alert-info'
          };
          const variant = variants[type] || 'alert-info';
          resultBox.className = 'mt-3 alert ' + variant;
          resultBox.textContent = message || '';
          resultBox.hidden = !message;
          if (message) {
            resultBox.classList.remove('d-none');
          } else {
            resultBox.classList.add('d-none');
          }
        };

        testForm.addEventListener('submit', () => {
          setLoading(true);
          showResult('info', '');
        });

        testForm.addEventListener('cms:admin:form:success', (event) => {
          setLoading(false);
          const detail = event && event.detail ? event.detail : {};
          const result = detail.result && detail.result.data ? detail.result.data : null;
          if (result && result.flash) {
            showResult(result.flash.type || 'info', result.flash.msg || '');
          } else if (result && typeof result.message === 'string') {
            const type = result.success === false ? 'danger' : 'info';
            showResult(type, result.message);
          } else {
            showResult('success', 'Testovací e-mail byl odeslán.');
          }
        });

        testForm.addEventListener('cms:admin:form:error', (event) => {
          setLoading(false);
          const detail = event && event.detail ? event.detail : {};
          const message = detail.message || 'Odeslání testu se nezdařilo.';
          showResult('danger', message);
        });
      }
    })();
  </script>
<?php
});
