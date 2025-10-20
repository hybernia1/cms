<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Cms\Admin\Auth\AuthService;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Utils\UploadPathFactory;
use Cms\Admin\View\ViewEngine;
use Core\Files\PathResolver;

abstract class BaseAdminController
{
    protected ViewEngine $view;
    protected AuthService $auth;

    public function __construct(string $baseViewsPath)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->view = new ViewEngine($baseViewsPath);
        $this->view->setBasePaths($this->resolveViewPaths($baseViewsPath));
        $this->auth = new AuthService();
    }

    /**
     * @return array<int,string>
     */
    private function resolveViewPaths(string $baseViewsPath): array
    {
        $paths = [];

        $realBase = realpath($baseViewsPath);
        if ($realBase !== false && is_dir($realBase)) {
            $paths[] = $realBase;
        }

        $adminRoot = realpath(dirname($baseViewsPath));
        if ($adminRoot !== false && is_dir($adminRoot)) {
            $paths[] = $adminRoot;
        }

        return $paths === [] ? [$baseViewsPath] : $paths;
    }

    final protected function isAjax(): bool
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        $accept = isset($_SERVER['HTTP_ACCEPT']) ? (string)$_SERVER['HTTP_ACCEPT'] : '';
        return str_contains($accept, 'application/json');
    }

    final protected function token(): string
    {
        if (empty($_SESSION['csrf_admin'])) {
            $_SESSION['csrf_admin'] = bin2hex(random_bytes(16));
        }

        return (string)$_SESSION['csrf_admin'];
    }

    final protected function assertCsrf(): void
    {
        $incoming = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
        if (empty($_SESSION['csrf_admin']) || !hash_equals((string)$_SESSION['csrf_admin'], (string)$incoming)) {
            if ($this->isAjax()) {
                $this->jsonError('CSRF token invalid', status: 419);
            }

            http_response_code(419);
            header('Content-Type: text/plain; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            echo 'CSRF token invalid';
            exit;
        }
    }

    final protected function flash(string $type, string $message): void
    {
        $_SESSION['_flash'] = ['type' => $type, 'msg' => $message];
    }

    final protected function redirect(string $url, ?string $flashType = null, ?string $flashMessage = null): never
    {
        if ($flashType !== null && $flashMessage !== null) {
            $this->flash($flashType, $flashMessage);
        }

        $flash = $_SESSION['_flash'] ?? null;
        $flashPayload = is_array($flash) ? $flash : null;

        if ($this->isAjax()) {
            $this->jsonRedirect($url, $flashPayload);
        }

        header('Location: ' . $url);
        exit;
    }

    /**
     * @param array<string,mixed> $data
     */
    final protected function jsonResponse(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @param array<string,mixed> $data
     */
    final protected function jsonSuccess(array $data = [], int $status = 200): never
    {
        $payload = ['success' => true, 'ok' => true];

        if ($data !== []) {
            $payload = array_merge($payload, $data);
        }

        $this->jsonResponse($payload, $status);
    }

    /**
     * @param array<string,mixed> $data
     */
    final protected function jsonError(string|array $errors, array $data = [], int $status = 400): never
    {
        $payload = array_merge(['success' => false, 'ok' => false], $data);
        $normalized = $this->normalizeErrors($errors);

        if (count($normalized) === 1) {
            $payload['error'] = $normalized[0];
        } elseif ($normalized !== []) {
            $payload['errors'] = $normalized;
        }

        $this->jsonResponse($payload, $status);
    }

    /**
     * @param array{type?:string,msg?:string}|null $flash
     */
    final protected function jsonRedirect(string $url, ?array $flash = null, int $status = 200): never
    {
        $success = $this->flashIndicatesSuccess($flash);
        $payload = [
            'success'  => $success,
            'ok'       => $success,
            'redirect' => $url,
        ];

        if ($flash !== null) {
            $payload['flash'] = [
                'type' => (string)($flash['type'] ?? ''),
                'msg'  => (string)($flash['msg'] ?? ''),
            ];
        }

        $this->jsonResponse($payload, $status);
    }

    /**
     * @return list<string>
     */
    private function normalizeErrors(string|array $errors): array
    {
        if (is_string($errors)) {
            $errors = [$errors];
        }

        $normalized = [];

        foreach ($errors as $error) {
            if (is_string($error)) {
                $trimmed = trim($error);
                if ($trimmed !== '') {
                    $normalized[] = $trimmed;
                }
                continue;
            }

            if (is_array($error)) {
                foreach ($this->normalizeErrors($error) as $nested) {
                    $normalized[] = $nested;
                }
            }
        }

        return $normalized;
    }

    final protected function renderAdmin(string $template, array $data = []): void
    {
        $payload = array_replace(
            [
                'currentUser' => $this->auth->user(),
                'flash'       => $this->pullFlash(),
                'csrf'        => $this->token(),
            ],
            $data,
        );

        if ($this->isAjax()) {
            $html = $this->captureView($template, $payload);
            $response = [
                'success' => true,
                'ok'      => true,
                'html'    => $html,
            ];

            if (!empty($payload['flash']) && is_array($payload['flash'])) {
                $response['flash'] = [
                    'type' => (string)($payload['flash']['type'] ?? ''),
                    'msg'  => (string)($payload['flash']['msg'] ?? ''),
                ];
            }

            if (!empty($payload['pageTitle'])) {
                $response['title'] = (string)$payload['pageTitle'];
            }

            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $this->view->render($template, $payload);
    }

    /**
     * Normalize pagination data to common shape used in admin listings.
     *
     * @param array<string,mixed> $paginated
     * @return array{page:int,per_page:int,total:int,pages:int}
     */
    final protected function paginationData(array $paginated, int $page, int $perPage): array
    {
        return [
            'page'     => (int)($paginated['page'] ?? $page),
            'per_page' => (int)($paginated['per_page'] ?? $perPage),
            'total'    => (int)($paginated['total'] ?? 0),
            'pages'    => (int)($paginated['pages'] ?? 1),
        ];
    }

    /**
     * Build closure for creating listing URLs with preserved filters.
     *
     * @param array<string,scalar|null> $baseQuery
     * @return callable(array<string,scalar|null>): string
     */
    final protected function listingUrlBuilder(array $baseQuery, string $route = 'admin.php'): callable
    {
        $normalized = $baseQuery;
        unset($normalized['page']);

        return static function (array $override = []) use ($route, $normalized): string {
            $query = array_merge($normalized, $override);

            if (!array_key_exists('page', $override)) {
                unset($query['page']);
            }

            $query = array_filter(
                $query,
                static fn($value): bool => $value !== null,
            );

            $qs = http_build_query($query);

            if ($qs === '') {
                return $route;
            }

            return $route . '?' . $qs;
        };
    }

    /**
     * Normalize created_at timestamps for listings to provide consistent raw/display (and optional ISO) values.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    final protected function normalizeCreatedAt(array $rows, bool $includeIso = false): array
    {
        if ($rows === []) {
            return [];
        }

        $settings = new CmsSettings();

        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rawValue = isset($row['created_at']) ? (string)$row['created_at'] : '';
            $createdAt = $rawValue !== '' ? $rawValue : null;
            $row['created_at_raw'] = $rawValue;

            $created = DateTimeFactory::fromStorage($createdAt);
            if ($created) {
                $row['created_at_display'] = $settings->formatDateTime($created);
                if ($includeIso) {
                    $row['created_at_iso'] = $created->format(\DateTimeInterface::ATOM);
                }
            } else {
                $row['created_at_display'] = $row['created_at_raw'];
                if ($includeIso) {
                    $row['created_at_iso'] = $createdAt;
                }
            }

            $normalized[] = $row;
        }

        return $normalized;
    }

    final protected function captureView(string $template, array $payload): string
    {
        ob_start();
        try {
            $this->view->render($template, $payload);
        } finally {
            $output = ob_get_clean();
        }

        return $output === false ? '' : $output;
    }

    final protected function uploadPaths(): PathResolver
    {
        return UploadPathFactory::forUploads();
    }

    private function pullFlash(): ?array
    {
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);

        return $flash;
    }

    private function flashIndicatesSuccess(?array $flash): bool
    {
        if ($flash === null) {
            return true;
        }

        $type = strtolower((string)($flash['type'] ?? ''));

        return $type !== 'danger';
    }
}
