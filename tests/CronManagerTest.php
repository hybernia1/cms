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

$queryFactory = static function () use ($pdo): Query {
    return new Query($pdo);
};

$currentTime = 1_700_000_000;
$scheduler = new Scheduler(static function () use (&$currentTime): int {
    return $currentTime;
});

$repository = new DatabaseTaskRepository($queryFactory);
$manager = new Manager($repository, $scheduler, new HookRegistry());

$executed = 0;
$manager->registerHook('fake_hook', function (string $payload) use (&$executed): void {
    if ($payload === 'payload') {
        $executed++;
    }
});

$manager->scheduleEvent('fake_hook', 300, ['payload']);

$currentTime += 200;
if ($manager->runDueTasks() !== 0) {
    throw new RuntimeException('Task should not run before due time.');
}

$currentTime += 100;
if ($manager->runDueTasks() !== 1) {
    throw new RuntimeException('Expected one cron task to run.');
}

if ($executed !== 1) {
    throw new RuntimeException('Cron hook callback was not executed.');
}

$logRow = $pdo->query("SELECT status, started_at, finished_at FROM cron_log WHERE hook = 'fake_hook' ORDER BY id DESC LIMIT 1")->fetch();
if (!$logRow) {
    throw new RuntimeException('Cron log entry missing.');
}

if ($logRow['status'] !== 'success') {
    throw new RuntimeException('Cron log entry should be marked as success.');
}

$lastRun = $repository->lastRun('fake_hook');
if ($lastRun !== (int)$logRow['finished_at']) {
    throw new RuntimeException('Last run timestamp does not match log entry.');
}

echo "Cron manager executes due tasks and logs runs.\n";
