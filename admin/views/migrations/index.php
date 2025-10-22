<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array<int,\Core\Database\Migrations\Migration> $all */
/** @var array<string,bool> $applied */
/** @var string $csrf */

$this->render('parts/layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($all,$applied,$csrf) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
  <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center gap-2 mb-3">
    <form method="post" action="admin.php?r=migrations&a=run" class="order-1" data-ajax data-migrations-form="run">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <button class="btn btn-success btn-sm" type="submit" data-migrations-submit>
        <i class="bi bi-play-circle me-1"></i>Spustit čekající migrace
      </button>
    </form>
    <form method="post" action="admin.php?r=migrations&a=rollback" class="order-2" onsubmit="return confirm('Rollback posledního batchu – pokračovat?');" data-ajax data-migrations-form="rollback">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <button class="btn btn-outline-warning btn-sm" type="submit" data-migrations-submit>
        <i class="bi bi-arrow-counterclockwise me-1"></i>Rollback posledního batchu
      </button>
    </form>
  </div>

  <div class="card mb-3" data-migrations-log hidden>
    <div class="card-header d-flex align-items-center gap-2">
      <span>Průběh</span>
      <div class="ms-auto d-flex align-items-center gap-2">
        <span class="badge text-bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle" data-migrations-status hidden></span>
        <div class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true" data-migrations-spinner hidden></div>
      </div>
    </div>
    <div class="card-body">
      <pre class="small mb-0" data-migrations-log-output></pre>
    </div>
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
            <tr data-migration-row data-migration-name="<?= $h($name) ?>">
              <td>
                <div class="fw-semibold"><?= $h($name) ?></div>
                <div class="small text-secondary"><?= $h($rf->getName()) ?> • <?= $h($file) ?></div>
              </td>
              <td data-migration-status>
                <?php if ($isApplied): ?>
                  <span class="badge text-bg-success-subtle text-success-emphasis border border-success-subtle" data-migration-status-label data-state="applied">Aplikováno</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle" data-migration-status-label data-state="pending">Čeká</span>
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
  <script>
  (function () {
    var STATUS_CLASSES = {
      applied: 'badge text-bg-success-subtle text-success-emphasis border border-success-subtle',
      pending: 'badge text-bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle',
      running: 'badge text-bg-info-subtle text-info-emphasis border border-info-subtle',
      error: 'badge text-bg-danger-subtle text-danger-emphasis border border-danger-subtle'
    };
    var STATUS_LABELS = {
      applied: 'Aplikováno',
      pending: 'Čeká',
      running: 'Probíhá…',
      error: 'Chyba'
    };

    function applyStatusBadge(badge, state) {
      if (!badge) {
        return;
      }
      var cls = STATUS_CLASSES[state] || STATUS_CLASSES.pending;
      badge.className = cls;
      badge.textContent = STATUS_LABELS[state] || STATUS_LABELS.pending;
      badge.setAttribute('data-state', state);
    }

    function appendLog(output, message) {
      if (!output || typeof message !== 'string' || !message) {
        return;
      }
      if (output.textContent !== '') {
        output.textContent += '\n';
      }
      output.textContent += message;
      output.scrollTop = output.scrollHeight;
    }

    function setButtonsDisabled(buttons, disabled) {
      buttons.forEach(function (btn) {
        if (!btn) {
          return;
        }
        btn.disabled = !!disabled;
      });
    }

    function updateStatuses(rows, appliedNames) {
      if (!rows || rows.length === 0) {
        return;
      }
      var appliedSet = new Set((appliedNames || []).map(function (name) { return String(name); }));
      rows.forEach(function (row) {
        if (!row || !row.isConnected) {
          return;
        }
        var name = row.getAttribute('data-migration-name') || '';
        var badge = row.querySelector('[data-migration-status-label]');
        if (!badge) {
          return;
        }
        if (appliedSet.has(name)) {
          applyStatusBadge(badge, 'applied');
        } else {
          applyStatusBadge(badge, 'pending');
        }
      });
    }

    function markRunning(rows, action) {
      rows.forEach(function (row) {
        if (!row || !row.isConnected) {
          return;
        }
        var badge = row.querySelector('[data-migration-status-label]');
        if (!badge) {
          return;
        }
        var state = badge.getAttribute('data-state');
        if (action === 'run' && state === 'pending') {
          applyStatusBadge(badge, 'running');
        } else if (action === 'rollback' && state === 'applied') {
          applyStatusBadge(badge, 'running');
        }
      });
    }

    function updateStatusIndicator(statusBadge, spinner, type, message) {
      if (!statusBadge) {
        return;
      }
      var classMap = {
        success: 'badge text-bg-success-subtle text-success-emphasis border border-success-subtle',
        error: 'badge text-bg-danger-subtle text-danger-emphasis border border-danger-subtle',
        info: 'badge text-bg-info-subtle text-info-emphasis border border-info-subtle'
      };
      var label = typeof message === 'string' ? message : '';
      var cls = classMap[type] || classMap.info;
      statusBadge.className = cls;
      statusBadge.textContent = label;
      statusBadge.hidden = !label;
      if (spinner) {
        spinner.hidden = true;
      }
    }

    function startProgress(container, statusBadge, spinner, output, buttons, rows, action) {
      if (container) {
        container.hidden = false;
      }
      if (statusBadge) {
        statusBadge.className = 'badge text-bg-info-subtle text-info-emphasis border border-info-subtle';
        statusBadge.textContent = action === 'rollback' ? 'Provádím rollback…' : 'Spouštím migrace…';
        statusBadge.hidden = false;
      }
      if (spinner) {
        spinner.hidden = false;
      }
      if (output) {
        output.textContent = '';
      }
      markRunning(rows, action);
      setButtonsDisabled(buttons, true);
    }

    function finishProgress(statusBadge, spinner, buttons) {
      if (spinner) {
        spinner.hidden = true;
      }
      setButtonsDisabled(buttons, false);
    }

    function initMigrations(root) {
      var scope = root || document;
      var container = scope.querySelector('[data-migrations-log]');
      if (!container || container.dataset.bound === '1') {
        return;
      }
      container.dataset.bound = '1';

      var statusBadge = container.querySelector('[data-migrations-status]');
      var spinner = container.querySelector('[data-migrations-spinner]');
      var output = container.querySelector('[data-migrations-log-output]');
      var buttons = [].slice.call(scope.querySelectorAll('[data-migrations-submit]'));
      var rows = [].slice.call(scope.querySelectorAll('[data-migration-row]'));

      var forms = [].slice.call(scope.querySelectorAll('form[data-migrations-form]'));
      if (forms.length === 0) {
        return;
      }

      var active = false;

      forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
          if (active) {
            event.preventDefault();
            return;
          }
          active = true;
          var action = form.getAttribute('data-migrations-form') || '';
          startProgress(container, statusBadge, spinner, output, buttons, rows, action);
        });

        form.addEventListener('cms:admin:form:success', function (event) {
          active = false;
          finishProgress(statusBadge, spinner, buttons);
          var detail = event && event.detail ? event.detail : {};
          var result = detail.result && detail.result.data ? detail.result.data : null;
          if (!result || typeof result !== 'object') {
            updateStatusIndicator(statusBadge, spinner, 'info', 'Hotovo.');
            return;
          }
          var logMessages = Array.isArray(result.log) ? result.log : [];
          logMessages.forEach(function (msg) { appendLog(output, msg); });
          if ((!logMessages || logMessages.length === 0) && typeof result.message === 'string') {
            appendLog(output, result.message);
          }
          var applied = Array.isArray(result.applied) ? result.applied : [];
          updateStatuses(rows, applied);
          var statusType = result.success ? 'success' : 'error';
          updateStatusIndicator(statusBadge, spinner, statusType, result.message || (result.success ? 'Hotovo.' : 'Došlo k chybě.'));
          if (!result.success) {
            var failedRows = rows.filter(function (row) {
              var badge = row.querySelector('[data-migration-status-label]');
              return badge && badge.getAttribute('data-state') === 'running';
            });
            failedRows.forEach(function (row) {
              var badge = row.querySelector('[data-migration-status-label]');
              applyStatusBadge(badge, 'error');
            });
          }
        });

        form.addEventListener('cms:admin:form:error', function (event) {
          active = false;
          finishProgress(statusBadge, spinner, buttons);
          updateStatusIndicator(statusBadge, spinner, 'error', (event && event.detail && event.detail.message) ? event.detail.message : 'Došlo k chybě.');
          var error = event && event.detail ? event.detail.error : null;
          var data = error && error.data ? error.data : null;
          var logMessages = data && Array.isArray(data.log) ? data.log : [];
          logMessages.forEach(function (msg) { appendLog(output, msg); });
          if ((!logMessages || logMessages.length === 0) && data && typeof data.message === 'string') {
            appendLog(output, data.message);
          }
        });
      });
    }

    function bootstrap(event) {
      var detail = event && event.detail ? event.detail : {};
      var root = detail.root || document;
      initMigrations(root);
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
      bootstrap();
    }

    document.addEventListener('cms:admin:navigated', bootstrap);
  })();
  </script>
<?php
});
