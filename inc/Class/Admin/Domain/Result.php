<?php
declare(strict_types=1);

namespace Cms\Admin\Domain;

final class Result
{
    private function __construct(
        private readonly bool $success,
        private readonly mixed $data,
        private readonly array $errors,
        private readonly ?string $message,
    ) {
    }

    public static function success(mixed $data = null, ?string $message = null): self
    {
        return new self(true, $data, [], $message);
    }

    /**
     * @param array<int,string>|string $errors
     */
    public static function failure(array|string $errors, ?string $message = null): self
    {
        $list = is_array($errors) ? array_values(array_map('strval', $errors)) : [(string) $errors];
        return new self(false, null, $list, $message);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return !$this->success;
    }

    public function data(): mixed
    {
        return $this->data;
    }

    /**
     * @return array<int,string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function message(): ?string
    {
        return $this->message;
    }
}
