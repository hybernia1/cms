<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Entities;

final class NewsletterCampaignLog
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        private ?int $id,
        private int $campaignId,
        private int $subscriberId,
        private string $status,
        private ?string $response,
        private ?string $error,
        private ?string $sentAt,
        private string $createdAt,
        private ?string $updatedAt,
    ) {
    }

    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            isset($row['campaign_id']) ? (int) $row['campaign_id'] : 0,
            isset($row['subscriber_id']) ? (int) $row['subscriber_id'] : 0,
            (string) ($row['status'] ?? self::STATUS_PENDING),
            isset($row['response']) ? (string) $row['response'] : null,
            isset($row['error']) ? (string) $row['error'] : null,
            isset($row['sent_at']) ? (string) $row['sent_at'] : null,
            (string) ($row['created_at'] ?? ''),
            isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }

    public function toRecord(): array
    {
        return [
            'campaign_id'   => $this->campaignId,
            'subscriber_id' => $this->subscriberId,
            'status'        => $this->status,
            'response'      => $this->response,
            'error'         => $this->error,
            'sent_at'       => $this->sentAt,
            'created_at'    => $this->createdAt,
            'updated_at'    => $this->updatedAt,
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

    public function subscriberId(): int
    {
        return $this->subscriberId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function response(): ?string
    {
        return $this->response;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function sentAt(): ?string
    {
        return $this->sentAt;
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
            $this->subscriberId,
            $this->status,
            $this->response,
            $this->error,
            $this->sentAt,
            $this->createdAt,
            $this->updatedAt,
        );
    }

    public function withStatus(string $status, ?string $sentAt = null, ?string $response = null, ?string $error = null, ?string $updatedAt = null): self
    {
        return new self(
            $this->id,
            $this->campaignId,
            $this->subscriberId,
            $status,
            $response ?? $this->response,
            $error ?? $this->error,
            $sentAt,
            $this->createdAt,
            $updatedAt,
        );
    }
}
