<?php
declare(strict_types=1);

namespace Core\Validation;

final class Validator
{
    private array $errors = [];

    public function require(array $data, string $field, string $msg = 'Pole je povinné'): self
    {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            $this->errors[$field][] = $msg;
        }
        return $this;
    }

    public function email(array $data, string $field, string $msg = 'Neplatný e-mail'): self
    {
        if (isset($data[$field]) && (string)$data[$field] !== '' && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = $msg;
        }
        return $this;
    }

    public function minLen(array $data, string $field, int $min, string $msg = ''): self
    {
        if (mb_strlen((string)($data[$field] ?? '')) < $min) {
            $this->errors[$field][] = $msg ?: "Minimálně {$min} znaků";
        }
        return $this;
    }

    public function enum(array $data, string $field, array $allowed, string $msg = 'Neplatná hodnota'): self
    {
        if (isset($data[$field]) && !in_array($data[$field], $allowed, true)) {
            $this->errors[$field][] = $msg;
        }
        return $this;
    }

    public function ok(): bool { return $this->errors === []; }
    public function errors(): array { return $this->errors; }
}
