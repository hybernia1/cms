<?php
declare(strict_types=1);

require __DIR__ . '/../load.php';

use Core\Database\Connect;
use Core\Database\Init;

$dbFile = __DIR__ . '/users_controller_test.sqlite';
if (is_file($dbFile)) {
    unlink($dbFile);
}

$pdoMain = new \PDO('sqlite:' . $dbFile);
$pdoMain->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$pdoMain->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

$schema = <<<SQL
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    role TEXT NOT NULL,
    active INTEGER NOT NULL,
    password_hash TEXT,
    created_at TEXT,
    updated_at TEXT,
    token TEXT,
    token_expire TEXT
);
SQL;
$pdoMain->exec($schema);

$config = [
    'db' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'database' => 'test',
        'user'     => 'test',
        'password' => 'test',
        'charset'  => 'utf8mb4',
    ],
];
$connect = new Connect($config);

$connectReflection = new ReflectionClass(Connect::class);
$pdoProperty = $connectReflection->getProperty('pdo');
$pdoProperty->setAccessible(true);
$pdoProperty->setValue($connect, $pdoMain);

$initReflection = new ReflectionClass(Init::class);
$connectProperty = $initReflection->getProperty('connect');
$connectProperty->setAccessible(true);
$connectProperty->setValue(null, $connect);
$bootedProperty = $initReflection->getProperty('booted');
$bootedProperty->setAccessible(true);
$bootedProperty->setValue(null, true);

$now = '2024-01-01 00:00:00';
$query = Init::query();
$query->table('users')->insertRow([
    'name'          => 'Parallel User',
    'email'         => 'parallel@example.com',
    'role'          => 'user',
    'active'        => 1,
    'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
    'created_at'    => $now,
    'updated_at'    => $now,
    'token'         => null,
    'token_expire'  => null,
])->execute();

$pdoOther = new \PDO('sqlite:' . $dbFile);
$pdoOther->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$pdoOther->exec("INSERT INTO users (name, email, role, active, password_hash, created_at, updated_at, token, token_expire) VALUES ('Other User', 'other@example.com', 'user', 1, 'hash', '$now', '$now', NULL, NULL)");

$newId = (int)$query->lastInsertId();
if ($newId !== 1) {
    throw new RuntimeException('Expected controller to receive ID 1, got ' . $newId);
}

$count = Init::query()->table('users')->count();
if ($count !== 2) {
    throw new RuntimeException('Expected two users to exist after concurrent inserts.');
}

echo "UsersController concurrent insert test passed.\n";

$pdoProperty->setValue($connect, null);
$connectProperty->setValue(null, null);
$bootedProperty->setValue(null, false);

$pdoOther = null;
$pdoMain = null;
unlink($dbFile);
