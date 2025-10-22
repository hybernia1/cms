<?php
declare(strict_types=1);

require __DIR__ . '/../load.php';

use Core\Database\SchemaChecker;

SchemaChecker::resetCache();

$fetchCalls = 0;
$tables = ['navigation_menus', 'navigation_items'];
$checker = new SchemaChecker(function () use (&$fetchCalls, $tables): array {
    $fetchCalls++;
    return $tables;
});

$checker->preload();

if ($checker->hasTable('navigation_menus') !== true) {
    throw new RuntimeException('Expected navigation_menus to be reported as existing.');
}

if ($checker->hasTable('navigation_items') !== true) {
    throw new RuntimeException('Expected navigation_items to be reported as existing.');
}

if ($checker->hasTable('unknown_table') !== false) {
    throw new RuntimeException('Unknown table must not be reported as existing.');
}

$checker->hasTable('navigation_items');
$checker->preload();

if ($fetchCalls !== 1) {
    throw new RuntimeException('SHOW TABLES fetcher should run only once per request. Calls: ' . $fetchCalls);
}

echo "SchemaChecker caches SHOW TABLES result per request.\n";
