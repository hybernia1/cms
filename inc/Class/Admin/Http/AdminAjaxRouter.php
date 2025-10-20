<?php
declare(strict_types=1);

namespace Cms\Admin\Http;

use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;

final class AdminAjaxRouter
{
    private static ?self $instance = null;

    /**
     * @var array<string, callable>
     */
    private array $handlers = [];

    private function __construct()
    {
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function register(string $action, callable $handler): void
    {
        $normalized = $this->normalize($action);
        if ($normalized === '') {
            throw new InvalidArgumentException('Action name must not be empty.');
        }

        $this->handlers[$normalized] = $handler;
    }

    public function unregister(string $action): void
    {
        $normalized = $this->normalize($action);
        unset($this->handlers[$normalized]);
    }

    public function has(string $action): bool
    {
        return isset($this->handlers[$this->normalize($action)]);
    }

    public function dispatch(?string $action): AjaxResponse
    {
        $normalized = $this->normalize($action);

        if ($normalized === '') {
            return AjaxResponse::error('Chybí parametr "action".', 400);
        }

        if (!isset($this->handlers[$normalized])) {
            return AjaxResponse::error('Požadovaná akce nebyla nalezena.', 404);
        }

        $handler = $this->handlers[$normalized];

        try {
            $result = $handler();
        } catch (Throwable $exception) {
            return AjaxResponse::error('Během zpracování došlo k chybě.', 500);
        }

        if ($result instanceof AjaxResponse) {
            return $result;
        }

        if ($result === null) {
            return AjaxResponse::success();
        }

        if (is_array($result)) {
            return AjaxResponse::success($result);
        }

        if (is_bool($result)) {
            return $result
                ? AjaxResponse::success()
                : AjaxResponse::error('Akce se nezdařila.', 400);
        }

        throw new UnexpectedValueException('Ajax handler must return an AjaxResponse or array.');
    }

    private function normalize(?string $action): string
    {
        if ($action === null) {
            return '';
        }

        return strtolower(trim($action));
    }
}
