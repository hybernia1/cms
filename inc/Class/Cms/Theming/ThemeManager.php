<?php
declare(strict_types=1);

namespace Cms\Theming;

use Cms\Settings\CmsSettings;

final class ThemeManager
{
    private string $themesBase;
    private string $active;

    public function __construct(?string $forceSlug = null)
    {
        // /themes
        $this->themesBase = dirname(__DIR__, 4) . '/themes';

        if ($forceSlug !== null && $forceSlug !== '') {
            $this->active = $forceSlug;
        } else {
            $settings = new CmsSettings();
            // preferuj nové API:
            if (method_exists($settings, 'themeSlug')) {
                $this->active = $settings->themeSlug() ?: 'classic';
            } else {
                // fallback na staré API (pokud by existovalo v jiné větvi)
                $this->active = method_exists($settings, 'getActiveTheme')
                    ? $settings->getActiveTheme()
                    : 'classic';
            }
        }
    }

    /** Slug aktivní šablony */
    public function activeSlug(): string
    {
        return $this->active;
    }

    /** Absolutní cesta k kořeni aktivní šablony (/themes/{slug}) */
    public function activePath(): string
    {
        return rtrim($this->themesBase, '/').'/'.$this->active;
    }

    /** Absolutní cesta k templates (/themes/{slug}/templates) */
    public function templateBasePath(): string
    {
        return $this->activePath().'/templates';
    }
}
