<?php
declare(strict_types=1);

namespace Cms\Http\Front;

use Cms\Auth\AuthService;
use Cms\Domain\Repositories\NavigationRepository;
use Cms\Domain\Services\SitemapService;
use Cms\Mail\TemplateManager;
use Cms\Settings\CmsSettings;
use Cms\Theming\ThemeManager;
use Cms\Theming\ThemeResolver;
use Cms\Utils\LinkGenerator;
use Cms\View\Assets;
use Cms\View\ViewEngine;

final class FrontServiceContainer
{
    private ThemeManager $themeManager;
    private ThemeResolver $themeResolver;
    private ViewEngine $viewEngine;
    private Assets $assets;
    private CmsSettings $settings;
    private LinkGenerator $urls;
    private SitemapService $sitemaps;
    private TemplateManager $mailTemplates;
    private AuthService $auth;
    private NavigationRepository $navigationRepository;
    /** @var array<int,array<string,mixed>>|null */
    private ?array $navigation = null;
    /** @var array<string,mixed>|null */
    private ?array $frontUser = null;

    public function __construct(
        ?ThemeManager $themeManager = null,
        ?CmsSettings $settings = null,
        ?TemplateManager $mailTemplates = null,
        ?AuthService $auth = null,
        ?NavigationRepository $navigationRepository = null
    ) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->settings = $settings ?? new CmsSettings();
        $this->themeManager = $themeManager ?? new ThemeManager();
        $this->themeResolver = new ThemeResolver($this->themeManager);

        $this->viewEngine = new ViewEngine($this->themeManager->templateBasePath());
        $this->viewEngine->setBasePaths($this->themeManager->templateBases());

        $this->assets = new Assets($this->themeManager);
        $this->urls = new LinkGenerator(null, $this->settings);
        $this->mailTemplates = $mailTemplates ?? new TemplateManager();
        $this->sitemaps = new SitemapService($this->urls, $this->settings);
        $this->auth = $auth ?? new AuthService();
        $this->navigationRepository = $navigationRepository ?? new NavigationRepository();
    }

    public function themeManager(): ThemeManager
    {
        return $this->themeManager;
    }

    public function resolver(): ThemeResolver
    {
        return $this->themeResolver;
    }

    public function view(): ViewEngine
    {
        return $this->viewEngine;
    }

    public function assets(): Assets
    {
        return $this->assets;
    }

    public function settings(): CmsSettings
    {
        return $this->settings;
    }

    public function urls(): LinkGenerator
    {
        return $this->urls;
    }

    public function sitemaps(): SitemapService
    {
        return $this->sitemaps;
    }

    public function mailTemplates(): TemplateManager
    {
        return $this->mailTemplates;
    }

    public function auth(): AuthService
    {
        return $this->auth;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function navigation(): array
    {
        if ($this->navigation === null) {
            $this->navigation = $this->navigationRepository->treeByLocation('primary');
        }

        return $this->navigation;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function frontUser(): ?array
    {
        if ($this->frontUser === null) {
            $this->frontUser = $this->auth->user();
        }

        return $this->frontUser;
    }

    public function refreshFrontUser(): void
    {
        $this->frontUser = $this->auth->user();
    }
}
