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
        $k    = trim($kind, "/\\");                // např. "auth/login"
        $type = (string)($params['type'] ?? '');   // např. post|page...

        $candidates = [];

        // 1) Přímý match vnořené cesty: templates/auth/login.php
        $candidates[] = $k;

        // 2) Pokud je vnořený tvar, zkus variantu se spojovníkem: auth-login.php
        if (str_contains($k, '/')) {
            $candidates[] = str_replace('/', '-', $k);
        }

        // 3) Pokud máme typ, zkus variantu s typem (např. single-post.php / single-page.php)
        if ($type !== '') {
            // k.php + typ
            $candidates[] = "{$k}-{$type}";
            // specialita pro single/page: když k='single' a type='page', zkus přímo page.php
            if ($k === 'single' && $type === 'page') {
                $candidates[] = 'page';
            }
        }

        // 4) typické fallbacky
        if ($k !== 'single') {
            $candidates[] = 'single'; // jeden univerzální detail
        }
        $candidates[] = 'index';      // úplně obecný fallback
        $candidates[] = '404';        // poslední možnost

        foreach ($candidates as $candidate) {
            if ($this->exists($candidate)) {
                return $candidate;
            }
        }
        // kdyby neexistovalo nic, vrať 404 (nebo index)
        if ($this->exists('404')) {
            return '404';
        }
        return 'index';
    }

    private function exists(string $candidate): bool
    {
        $candidate = ltrim($candidate, '/');
        foreach ($this->tm->templateBases() as $base) {
            $file = rtrim($base, '/').'/'.$candidate;
            if (!str_ends_with($file, '.php')) {
                $file .= '.php';
            }
            if (is_file($file)) {
                return true;
            }
        }
        return false;
    }
}