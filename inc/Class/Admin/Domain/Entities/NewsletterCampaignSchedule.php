<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Entities;

final class NewsletterCampaignSchedule
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        private ?int $id,
        private int $campaignId,
        private string $scheduledFor,
        private string $status,
        private string $createdAt,
        private ?string $processedAt = null,
    ) {
    }

    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            isset($row['campaign_id']) ? (int) $row['campaign_id'] : 0,
            (string) ($row['scheduled_for'] ?? ''),
            (string) ($row['status'] ?? self::STATUS_PENDING),
            (string) ($row['created_at'] ?? ''),
            isset($row['processed_at']) ? (string) $row['processed_at'] : null,
        );
    }

    public function toRecord(): array
    {
        return [
            'campaign_id'   => $this->campaignId,
            'scheduled_for' => $this->scheduledFor,
            'status'        => $this->status,
            'created_at'    => $this->createdAt,
            'processed_at'  => $this->processedAt,
        ];
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function campaignId(): int
    {
        return $this->campaignId;
    }

    public function scheduledFor(): string
    {
        return $this->scheduledFor;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): string
    {
        return $this->createdAt;
    }

    public function processedAt(): ?string
    {
        return $this->processedAt;
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->campaignId,
            $this->scheduledFor,
            $this->status,
            $this->createdAt,
            $this->processedAt,
        );
    }

    public function withStatus(string $status, ?string $processedAt = null): self
    {
        return new self(
            $this->id,
            $this->campaignId,
            $this->scheduledFor,
            $status,
            $this->createdAt,
            $processedAt,
        );
    }
}
