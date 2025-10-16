<?php
declare(strict_types=1);

namespace Cms\Theming;

final class ThemeResolver
{
    public function __construct(private ThemeManager $tm) {}

    /**
     * $kind může být:
     *  - 'home', 'single', 'page', 'archive', 'search', ...
     *  - VNOŘENÁ cesta: 'auth/login', 'auth/register', ...
     *
     * Použijeme několik kandidátů a první existující vrátíme.
     */
    public function resolve(string $kind, array $params = []): string
    {
        $base = rtrim($this->tm->templateBasePath(), '/');
        $k    = trim($kind, "/\\");                // např. "auth/login"
        $type = (string)($params['type'] ?? '');   // např. post|page...

        $candidates = [];

        // 1) Přímý match vnořené cesty: templates/auth/login.php
        $candidates[] = "{$base}/{$k}.php";

        // 2) Pokud je vnořený tvar, zkus variantu se spojovníkem: auth-login.php
        if (str_contains($k, '/')) {
            $candidates[] = "{$base}/" . str_replace('/', '-', $k) . ".php";
        }

        // 3) Pokud máme typ, zkus variantu s typem (např. single-post.php / single-page.php)
        if ($type !== '') {
            // k.php + typ
            $candidates[] = "{$base}/{$k}-{$type}.php";
            // specialita pro single/page: když k='single' a type='page', zkus přímo page.php
            if ($k === 'single' && $type === 'page') {
                $candidates[] = "{$base}/page.php";
            }
        }

        // 4) typické fallbacky
        if ($k !== 'single') {
            $candidates[] = "{$base}/single.php"; // jeden univerzální detail
        }
        $candidates[] = "{$base}/index.php";      // úplně obecný fallback
        $candidates[] = "{$base}/404.php";        // poslední možnost

        foreach ($candidates as $file) {
            if (is_file($file)) {
                return $file;
            }
        }
        // kdyby neexistovalo nic, vrať 404 (nebo index)
        $fallback = "{$base}/404.php";
        return is_file($fallback) ? $fallback : "{$base}/index.php";
    }
}