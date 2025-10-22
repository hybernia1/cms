<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Entities;

final class NewsletterCampaignSchedule
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';

    public function __construct(
        private ?int $id,
        private int $campaignId,
        private string $status,
        private ?string $startAt,
        private ?string $endAt,
        private int $intervalMinutes,
        private int $maxAttempts,
        private int $attempts,
        private ?string $nextRunAt,
        private ?string $lastRunAt,
        private string $createdAt,
        private ?string $updatedAt = null,
    ) {
    }

    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            isset($row['campaign_id']) ? (int) $row['campaign_id'] : 0,
            (string) ($row['status'] ?? self::STATUS_DRAFT),
            isset($row['start_at']) ? (string) $row['start_at'] : null,
            isset($row['end_at']) ? (string) $row['end_at'] : null,
            isset($row['interval_minutes']) ? (int) $row['interval_minutes'] : 0,
            isset($row['max_attempts']) ? (int) $row['max_attempts'] : 1,
            isset($row['attempts']) ? (int) $row['attempts'] : 0,
            isset($row['next_run_at']) ? (string) $row['next_run_at'] : null,
            isset($row['last_run_at']) ? (string) $row['last_run_at'] : null,
            (string) ($row['created_at'] ?? ''),
            isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }

    public function toRecord(): array
    {
        return [
            'campaign_id'     => $this->campaignId,
            'status'          => $this->status,
            'start_at'        => $this->startAt,
            'end_at'          => $this->endAt,
            'interval_minutes' => $this->intervalMinutes,
            'max_attempts'     => $this->maxAttempts,
            'attempts'         => $this->attempts,
            'next_run_at'      => $this->nextRunAt,
            'last_run_at'      => $this->lastRunAt,
            'created_at'      => $this->createdAt,
            'updated_at'      => $this->updatedAt,
        ];
    }

    public function toArray(): array
    {
        return array_merge(['id' => $this->id], $this->toRecord());
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function campaignId(): int
    {
        return $this->campaignId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function startAt(): ?string
    {
        return $this->startAt;
    }

    public function endAt(): ?string
    {
        return $this->endAt;
    }

    public function intervalMinutes(): int
    {
        return $this->intervalMinutes;
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function nextRunAt(): ?string
    {
        return $this->nextRunAt;
    }

    public function lastRunAt(): ?string
    {
        return $this->lastRunAt;
    }

    public function createdAt(): string
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->campaignId,
            $this->status,
            $this->startAt,
            $this->endAt,
            $this->intervalMinutes,
            $this->maxAttempts,
            $this->attempts,
            $this->nextRunAt,
            $this->lastRunAt,
            $this->createdAt,
            $this->updatedAt,
        );
    }

    public function withStatus(string $status, ?string $updatedAt = null): self
    {
        return new self(
            $this->id,
            $this->campaignId,
            $status,
            $this->startAt,
            $this->endAt,
            $this->intervalMinutes,
            $this->maxAttempts,
            $this->attempts,
            $this->nextRunAt,
            $this->lastRunAt,
            $this->createdAt,
            $updatedAt,
        );
    }
}
