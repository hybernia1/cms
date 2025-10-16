<?php
declare(strict_types=1);

use Core\Database\Init as DB;
use Cms\Http\FrontController;

require_once __DIR__ . '/load.php';

$config = require __DIR__ . '/config.php';
DB::boot($config);

$fc = new FrontController();
$fc->handle();
