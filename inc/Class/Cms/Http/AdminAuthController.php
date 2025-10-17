<?php
declare(strict_types=1);

namespace Cms\Http;

use Cms\Auth\AuthService;
final class AdminAuthController
{
    private AuthService $auth;

    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $this->auth = new AuthService();
    }

    public function handle(string $action): void
    {
        switch ($action) {
            case 'login':
            default:
                cms_redirect_to_front_login();
                return;

            case 'logout':
                $this->auth->logout();
                cms_redirect_to_front_login(true);
        }
    }
}
