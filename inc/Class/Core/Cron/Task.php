<?php
declare(strict_types=1);

namespace Core\Cron;

final class Task
{
    private ?int $id;
    private string $hook;
    /** @var array<int|string,mixed> */
    private array $args;
    private int $scheduledAt;
    private ?int $intervalSeconds;

    /**
     * @param array<int|string,mixed> $args
     */
    public function __construct(
        ?int $id,
        string $hook,
        array $args,
        int $scheduledAt,
        ?int $intervalSeconds
    ) {
        $this->id = $id;
        $this->hook = $hook;
        $this->args = $args;
        $this->scheduledAt = $scheduledAt;
        $this->intervalSeconds = $intervalSeconds;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function hook(): string
    {
        return $this->hook;
    }

    /**
     * @return array<int|string,mixed>
     */
    public function args(): array
    {
        return $this->args;
    }

    public function scheduledAt(): int
    {
        return $this->scheduledAt;
    }

    public function intervalSeconds(): ?int
    {
        return $this->intervalSeconds;
    }

    public function withId(int $id): self
    {
        $clone = clone $this;
        $clone->id = $id;
        return $clone;
    }

    public function withScheduledAt(int $timestamp): self
    {
        $clone = clone $this;
        $clone->scheduledAt = $timestamp;
        return $clone;
    }
}
