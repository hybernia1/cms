<?php
declare(strict_types=1);

namespace Cms\Models;

/**
 * Lightweight data container representing a database record.
 */
abstract class BaseModel
{
    /** @var array<string,mixed> */
    protected array $attributes = [];

    /**
     * @param array<string,mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function fill(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
