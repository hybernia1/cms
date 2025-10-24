<?php
declare(strict_types=1);

use Cms\Front\Support\SimpleCache;

if (!function_exists('cms_front_cache_invalidate')) {
    function cms_front_cache_invalidate(?string $namespace = null): void
    {
        $cache = new SimpleCache();
        if ($namespace === null || trim($namespace) === '') {
            $cache->clear();
            return;
        }

        $cache->clearNamespace($namespace);
    }
}
