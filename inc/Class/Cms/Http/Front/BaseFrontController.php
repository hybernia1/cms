<?php
declare(strict_types=1);

namespace Cms\Http\Front;

use Cms\Auth\Authorization;
use Cms\Front\View\BaseFrontViewModel;
use Cms\Front\View\FrontViewContext;
use Cms\Front\View\NotFoundViewModel;
use Cms\Utils\LinkGenerator;

abstract class BaseFrontController
{
    protected readonly FrontViewContext $viewContext;

    public function __construct(protected readonly FrontServiceContainer $services)
    {
        $this->viewContext = new FrontViewContext($services);
    }

    protected function render(string $templateKind, BaseFrontViewModel $model, array $params = []): void
    {
        $view = $this->services->view();
        $view->share($this->viewContext->shared());
        $template = $this->services->resolver()->resolve($templateKind, $params);
        $view->renderLayout('layouts/base', $template, $model->toArray());
    }

    protected function renderNotFound(): void
    {
        http_response_code(404);
        $this->render('404', new NotFoundViewModel($this->viewContext));
    }

    protected function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    protected function redirectBack(): never
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? './';
        $this->redirect((string)$ref);
    }

    protected function redirectToPost(string $slug): never
    {
        $this->redirect($this->services->urls()->post($slug));
    }

    protected function tokenPublic(): string
    {
        if (empty($_SESSION['csrf_public'])) {
            $_SESSION['csrf_public'] = bin2hex(random_bytes(16));
        }
        return (string)$_SESSION['csrf_public'];
    }

    protected function assertCsrfPublic(): void
    {
        $incoming = (string)($_POST['csrf'] ?? '');
        if (empty($_SESSION['csrf_public']) || !hash_equals((string)$_SESSION['csrf_public'], $incoming)) {
            http_response_code(419);
            echo 'CSRF token invalid';
            exit;
        }
    }

    protected function writeFrontFlash(string $type, string $message): void
    {
        $_SESSION['_f_flash'] = ['type' => $type, 'msg' => $message];
    }

    /**
     * @return array{type:string,msg:string}|null
     */
    protected function readFrontFlash(): ?array
    {
        $flash = $_SESSION['_f_flash'] ?? null;
        unset($_SESSION['_f_flash']);
        if (!is_array($flash)) {
            return null;
        }

        $type = isset($flash['type']) ? (string)$flash['type'] : '';
        $msg  = isset($flash['msg']) ? (string)$flash['msg'] : '';
        if ($type === '' || $msg === '') {
            return null;
        }

        return ['type' => $type, 'msg' => $msg];
    }

    protected function isAdmin(?array $user): bool
    {
        return Authorization::isAdmin($user);
    }

    protected function linkGenerator(): LinkGenerator
    {
        return $this->services->urls();
    }
}
