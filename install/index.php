<?php
declare(strict_types=1);

/**
 * /install/index.php
 * ------------------
 * Vícekrokový instalátor:
 * 1) DB připojení + zápis config.php
 * 2) Vytvoření tabulek z tables.php
 * 3) Vytvoření admin účtu + základních settings
 *
 * Požadavky:
 * - existuje /load.php (autoload), ale nemusí existovat /config.php (instalátor jej vytvoří)
 */

use Core\Database\Init as DB;

session_start();
$BASE = dirname(__DIR__);           // root projektu
$CONFIG_FILE = $BASE . '/config.php';
$TABLES_FILE = __DIR__ . '/tables.php';

// helpers
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function is_cli(): bool { return (PHP_SAPI === 'cli'); }

// načti stávající config (pokud je)
$haveConfig = is_file($CONFIG_FILE);
$config = $haveConfig ? (require $CONFIG_FILE) : [
    'debug' => true,
    'db' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'port'     => 3306,
        'database' => '',
        'user'     => '',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],
];

// krok
$step = isset($_GET['step']) ? max(1, (int)$_GET['step']) : 1;

// POST akce
$flash = null;

// KROK 1: uložení config.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 1) {
    try {
        $db = [
            'driver'   => 'mysql',
            'host'     => trim((string)($_POST['db_host'] ?? 'localhost')),
            'port'     => (int)($_POST['db_port'] ?? 3306),
            'database' => trim((string)($_POST['db_name'] ?? '')),
            'user'     => trim((string)($_POST['db_user'] ?? '')),
            'password' => (string)($_POST['db_pass'] ?? ''),
            'charset'  => 'utf8mb4',
        ];
        $debug = isset($_POST['debug']) ? (bool)$_POST['debug'] : true;

        if ($db['database'] === '' || $db['user'] === '') {
            throw new RuntimeException('Vyplň název databáze a uživatele.');
        }

        // otestuj připojení
        require_once $BASE . '/load.php';
        DB::boot(['debug'=>$debug, 'db'=>$db]);
        DB::pdo()->query('SELECT 1');

        // vygeneruj config.php
        $configData = [
            'debug' => $debug,
            'db' => [
                'driver'   => 'mysql',
                'host'     => $db['host'],
                'port'     => $db['port'],
                'database' => $db['database'],
                'user'     => $db['user'],
                'password' => $db['password'],
                'charset'  => 'utf8mb4',
            ],
        ];

        $exported = var_export($configData, true);
        if (!is_string($exported)) {
            throw new RuntimeException('Nepodařilo se serializovat konfiguraci.');
        }

        $cfg = "<?php\ndeclare(strict_types=1);\n\nreturn " . $exported;
        if (!str_ends_with($cfg, "\n")) {
            $cfg .= "\n";
        }

        if (file_put_contents($CONFIG_FILE, $cfg) === false) {
            throw new RuntimeException('Nelze zapsat config.php (oprávnění?).');
        }

        header('Location: ?step=2');
        exit;

    } catch (Throwable $e) {
        $flash = ['type'=>'danger','msg'=>$e->getMessage()];
    }
}

// KROK 2: vytvoření tabulek
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    try {
        if (!is_file($CONFIG_FILE)) throw new RuntimeException('Chybí config.php.');
        require_once $BASE . '/load.php';
        $cfg = require $CONFIG_FILE;
        DB::boot($cfg);

        if (!is_file($TABLES_FILE)) {
            throw new RuntimeException('Chybí /install/tables.php.');
        }
        /** @var array<int,string> $sqls */
        $sqls = require $TABLES_FILE;

        $pdo = DB::pdo();
        foreach ($sqls as $sql) {
            $pdo->exec($sql);
        }

        // vlož default settings (id=1) pokud chybí
        $exists = DB::query()->table('settings')->select(['COUNT(*) AS c'])->first();
        if ((int)($exists['c'] ?? 0) === 0) {
            DB::query()->table('settings')->insert([
                'id'         => 1,
                'site_title' => 'Můj web',
                'site_email' => '',
                'data'       => json_encode(new stdClass(), JSON_UNESCAPED_UNICODE),
                'updated_at' => null,
            ])->execute();
        }

        header('Location: ?step=3');
        exit;

    } catch (Throwable $e) {
        $flash = ['type'=>'danger','msg'=>$e->getMessage()];
    }
}

// KROK 3: vytvoření admin účtu + základních settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 3) {
    try {
        if (!is_file($CONFIG_FILE)) throw new RuntimeException('Chybí config.php.');
        require_once $BASE . '/load.php';
        $cfg = require $CONFIG_FILE;
        DB::boot($cfg);

        $name  = trim((string)($_POST['admin_name'] ?? ''));
        $email = trim((string)($_POST['admin_email'] ?? ''));
        $pass  = (string)($_POST['admin_pass'] ?? '');
        $title = trim((string)($_POST['site_title'] ?? 'Můj web'));
        $webmail = trim((string)($_POST['site_email'] ?? ''));

        if ($name === '' || $email === '' || $pass === '') {
            throw new RuntimeException('Vyplň jméno, e-mail a heslo.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('E-mail není platný.');
        }
        if (strlen($pass) < 8) {
            throw new RuntimeException('Heslo musí mít alespoň 8 znaků.');
        }

        // existuje admin?
        $existing = DB::query()->table('users')->select(['id'])->where('email','=', $email)->first();
        if ($existing) {
            throw new RuntimeException('Uživatel s tímto e-mailem již existuje.');
        }

        $hash = password_hash($pass, PASSWORD_DEFAULT);

        DB::query()->table('users')->insert([
            'name'          => $name,
            'email'         => $email,
            'password_hash' => $hash,
            'active'        => 1,
            'role'          => 'admin',
            'token'         => null,
            'token_expire'  => null,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => null,
        ])->insertGetId();

        // settings update
        DB::query()->table('settings')->update([
            'site_title' => $title,
            'site_email' => $webmail,
            'updated_at' => date('Y-m-d H:i:s'),
        ])->where('id','=',1)->execute();

        $doneUrl = dirname($_SERVER['SCRIPT_NAME'] ?? '/install') . '/';
        $doneUrl = rtrim($doneUrl, '/') . '/';

        $flash = ['type'=>'success','msg'=>'Instalace dokončena.'];
        header('Location: ?step=4');
        exit;

    } catch (Throwable $e) {
        $flash = ['type'=>'danger','msg'=>$e->getMessage()];
    }
}

// UI
?>
<!doctype html>
<html lang="cs" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <title>Instalátor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .container-narrow { max-width: 880px; }
    .stepper .step { padding:.5rem 1rem; border-radius:.5rem; }
    .step.active { background: rgba(255,255,255,.08); }
    .code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
  </style>
</head>
<body>
<div class="container container-narrow py-4">

  <h1 class="h3 mb-4">Instalátor</h1>

  <div class="stepper d-flex gap-2 mb-4">
    <div class="step <?= $step===1?'active':'' ?>">1) Připojení &amp; config.php</div>
    <div class="step <?= $step===2?'active':'' ?>">2) Tabulky</div>
    <div class="step <?= $step===3?'active':'' ?>">3) Admin &amp; Nastavení</div>
    <div class="step <?= $step===4?'active':'' ?>">Hotovo</div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?=h($flash['type'])?>"><?=h($flash['msg'])?></div>
  <?php endif; ?>

  <?php if ($step === 1): ?>
    <?php if ($haveConfig): ?>
      <div class="alert alert-warning">
        Soubor <span class="code">config.php</span> již existuje. Můžeš pokračovat na <a href="?step=2" class="alert-link">Krok 2</a>.
      </div>
    <?php endif; ?>
    <form method="post">
      <div class="card mb-3">
        <div class="card-header">Připojení k databázi</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Host</label>
              <input name="db_host" class="form-control" value="<?=h((string)($config['db']['host'] ?? 'localhost'))?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Port</label>
              <input name="db_port" type="number" class="form-control" value="<?=h((string)($config['db']['port'] ?? 3306))?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Charset</label>
              <input class="form-control" value="utf8mb4" disabled>
            </div>
            <div class="col-md-4">
              <label class="form-label">Database</label>
              <input name="db_name" class="form-control" value="<?=h((string)($config['db']['database'] ?? ''))?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">User</label>
              <input name="db_user" class="form-control" value="<?=h((string)($config['db']['user'] ?? ''))?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Password</label>
              <input name="db_pass" type="password" class="form-control" value="<?=h((string)($config['db']['password'] ?? ''))?>">
            </div>
            <div class="col-12 form-check mt-2">
              <input class="form-check-input" type="checkbox" id="debug" name="debug" value="1" <?=!empty($config['debug'])?'checked':''?>>
              <label class="form-check-label" for="debug">Zapnout debug (zobrazení chyb v prohlížeči)</label>
            </div>
          </div>
        </div>
        <div class="card-footer d-flex gap-2">
          <button class="btn btn-primary" type="submit">Uložit & pokračovat</button>
          <?php if ($haveConfig): ?>
            <a class="btn btn-outline-secondary" href="?step=2">Přeskočit</a>
          <?php endif; ?>
        </div>
      </div>
    </form>

  <?php elseif ($step === 2): ?>
    <form method="post">
      <div class="card mb-3">
        <div class="card-header">Vytvoření tabulek</div>
        <div class="card-body">
          <p>Načte se <span class="code">/install/tables.php</span> a spustí se všechny <code>CREATE TABLE IF NOT EXISTS</code>.</p>
          <p>Ujisti se, že už existuje <span class="code">config.php</span>.</p>
        </div>
        <div class="card-footer d-flex gap-2">
          <a class="btn btn-outline-secondary" href="?step=1">Zpět</a>
          <button class="btn btn-primary" type="submit">Vytvořit tabulky</button>
        </div>
      </div>
    </form>

  <?php elseif ($step === 3): ?>
    <form method="post">
      <div class="card mb-3">
        <div class="card-header">Admin & Základní nastavení</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Admin jméno</label>
              <input name="admin_name" class="form-control" placeholder="Např. Nikola" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Admin e-mail</label>
              <input name="admin_email" type="email" class="form-control" placeholder="you@example.com" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Admin heslo</label>
              <input name="admin_pass" type="password" class="form-control" minlength="8" required>
              <div class="form-text">Minimálně 8 znaků.</div>
            </div>
            <div class="col-md-6"></div>
            <div class="col-md-6">
              <label class="form-label">Název webu</label>
              <input name="site_title" class="form-control" value="Můj web">
            </div>
            <div class="col-md-6">
              <label class="form-label">E-mail webu</label>
              <input name="site_email" type="email" class="form-control" placeholder="info@example.com">
            </div>
          </div>
        </div>
        <div class="card-footer d-flex gap-2">
          <a class="btn btn-outline-secondary" href="?step=2">Zpět</a>
          <button class="btn btn-primary" type="submit">Dokončit instalaci</button>
        </div>
      </div>
    </form>

  <?php else: /* step 4 */ ?>
    <div class="card">
      <div class="card-header">Hotovo 🎉</div>
      <div class="card-body">
        <p>Instalace proběhla úspěšně. Můžeš smazat adresář <span class="code">/install</span> nebo jej uzamknout.</p>
        <ul>
          <li><span class="code">config.php</span> je vytvořen.</li>
          <li>Tabulky existují.</li>
          <li>Admin účet byl založen.</li>
        </ul>
        <a class="btn btn-primary" href="../">Přejít na homepage</a>
      </div>
    </div>
  <?php endif; ?>

</div>
</body>
</html>
