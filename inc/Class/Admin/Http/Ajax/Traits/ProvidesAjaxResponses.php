<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Traits;

use Cms\Admin\Http\AjaxResponse;

trait ProvidesAjaxResponses
{
    private function successResponse(?string $message = null, array $data = [], ?string $flashType = 'success'): AjaxResponse
    {
        if ($message !== null) {
            $data = ['message' => $message] + $data;
            if ($flashType !== null) {
                $data['flash'] = ['type' => $flashType, 'msg' => $message];
                $this->setFlash($flashType, $message);
            }
        }

        return AjaxResponse::success($data);
    }

    private function errorResponse(string|array $errors, int $status = 400, ?string $message = null, ?string $flashType = 'danger', array $data = []): AjaxResponse
    {
        if ($message === null) {
            $message = is_array($errors) ? trim(implode(' ', $errors)) : trim($errors);
        }

        if ($message !== null && $message !== '') {
            $data = ['message' => $message] + $data;
            if ($flashType !== null) {
                $data['flash'] = ['type' => $flashType, 'msg' => $message];
                $this->setFlash($flashType, $message);
            }
        }

        return AjaxResponse::error($errors, $status, $data);
    }

    private function setFlash(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['_flash'] = ['type' => $type, 'msg' => $message];
    }
}
