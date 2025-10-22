<?php
declare(strict_types=1);

require __DIR__ . '/../load.php';

use Core\Cron\DatabaseTaskRepository;
use Core\Cron\HookRegistry;
use Core\Cron\Manager;
use Core\Cron\Scheduler;
use Core\Database\Query;

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pdo->exec(<<<SQL
CREATE TABLE cron_tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    hook TEXT NOT NULL,
    args TEXT NOT NULL,
    scheduled_at INTEGER NOT NULL,
    interval_seconds INTEGER NULL
);
SQL);

$pdo->exec(<<<SQL
CREATE TABLE cron_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NULL,
    hook TEXT NOT NULL,
    status TEXT NOT NULL,
    started_at INTEGER NOT NULL,
    finished_at INTEGER NULL,
    message TEXT NULL
);
SQL);

$currentTime = time();
$scheduler = new Scheduler(static function () use (&$currentTime): int {
    return $currentTime;
});

$queryFactory = static function () use ($pdo): Query {
    return new Query($pdo);
};

$repository = new DatabaseTaskRepository($queryFactory);
$manager = new Manager($repository, $scheduler, new HookRegistry());

cms_register_default_cron_hooks($manager);

$row = $pdo->query("SELECT scheduled_at, interval_seconds FROM cron_tasks WHERE hook = 'core/debug-heartbeat' LIMIT 1")->fetch();
if (!$row) {
    throw new RuntimeException('Cron debug heartbeat was not scheduled.');
}

if ((int)$row['interval_seconds'] !== 3600) {
    throw new RuntimeException('Cron debug heartbeat should run hourly.');
}

$currentTime = (int)$row['scheduled_at'] + 3600;

$executed = $manager->runDueTasks();
if ($executed !== 1) {
    throw new RuntimeException('Cron debug heartbeat should execute when due.');
}

$logRow = $pdo->query("SELECT status FROM cron_log WHERE hook = 'core/debug-heartbeat' ORDER BY id DESC LIMIT 1")->fetch();
if (!$logRow || $logRow['status'] !== 'success') {
    throw new RuntimeException('Cron debug heartbeat execution should be logged as success.');
}

echo "Cron debug heartbeat is scheduled and runs.\n";
