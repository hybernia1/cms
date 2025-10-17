<?php
declare(strict_types=1);

use Cms\Http\FrontController;

require_once __DIR__ . '/load.php';

cms_bootstrap_config_or_redirect();

$fc = new FrontController();
$fc->handle();
