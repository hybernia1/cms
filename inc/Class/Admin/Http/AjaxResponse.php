<?php
declare(strict_types=1);

namespace Cms\Admin\Http;

final class AjaxResponse
{
    private bool $success;
    /**
     * @var mixed
     */
    private $data;

    /**
     * @var list<string>
     */
    private array $errors;
    private int $status;

    private function __construct(bool $success, mixed $data, array $errors, int $status)
    {
        $this->success = $success;
        $this->data = $data;
        $this->errors = $errors;
        $this->status = $status;
    }

    public static function success(mixed $data = null, int $status = 200): self
    {
        return new self(true, $data, [], $status);
    }

    public static function error(string|array $errors, int $status = 400, mixed $data = null): self
    {
        return new self(false, $data, self::normalizeErrors($errors), $status);
    }

    public static function fromArray(array $payload, int $status = 200): self
    {
        $success = (bool)($payload['success'] ?? false);
        $data = $payload['data'] ?? null;
        $errors = isset($payload['errors']) ? self::normalizeErrors($payload['errors']) : [];

        return new self($success, $data, $errors, $status);
    }

    public function withStatus(int $status): self
    {
        return new self($this->success, $this->data, $this->errors, $status);
    }

    public function payload(): array
    {
        $payload = ['success' => $this->success];

        if ($this->data !== null) {
            $payload['data'] = $this->data;
        }

        if ($this->errors !== []) {
            $payload['errors'] = $this->errors;
        }

        return $payload;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function send(): never
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->payload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @return list<string>
     */
    private static function normalizeErrors(string|array $errors): array
    {
        if (is_string($errors)) {
            $errors = [$errors];
        }

        $normalized = [];

        foreach ($errors as $value) {
            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed !== '') {
                    $normalized[] = $trimmed;
                }
                continue;
            }

            if (is_array($value)) {
                foreach (self::normalizeErrors($value) as $nested) {
                    $normalized[] = $nested;
                }
            }
        }

        return $normalized;
    }
}
