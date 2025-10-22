<?php
declare(strict_types=1);

require __DIR__ . '/load.php';

cms_bootstrap_config_or_redirect();

cms_cron_manager()->runDueTasks();
