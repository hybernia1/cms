<?php
declare(strict_types=1);

$_GET['r'] = $_GET['r'] ?? 'orders';
$_GET['a'] = $_GET['a'] ?? 'detail';
require __DIR__ . '/../admin.php';
