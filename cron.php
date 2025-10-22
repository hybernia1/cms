<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

define('CMS_CRON', true);

require __DIR__ . '/load.php';

cms_bootstrap_config_or_redirect();
cms_bootstrap_cron(runTick: false);

$cron = \Core\Cron\Cron::instance();

if (!$cron->ensureReady()) {
    fwrite(STDERR, "Cron tabulky nejsou připravené.\n");
    exit(0);
}

$cron->tick();
