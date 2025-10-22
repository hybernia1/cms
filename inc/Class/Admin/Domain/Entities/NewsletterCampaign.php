<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Entities;

final class NewsletterCampaign
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENDING = 'sending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        private ?int $id,
        private string $subject,
        private string $body,
        private string $status,
        private int $recipientsCount,
        private int $sentCount,
        private int $failedCount,
        private ?int $createdBy,
        private string $createdAt,
        private ?string $updatedAt = null,
        private ?string $sentAt = null,
    ) {
    }

    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) ($row['subject'] ?? ''),
            (string) ($row['body'] ?? ''),
            (string) ($row['status'] ?? self::STATUS_DRAFT),
            isset($row['recipients_count']) ? (int) $row['recipients_count'] : 0,
            isset($row['sent_count']) ? (int) $row['sent_count'] : 0,
            isset($row['failed_count']) ? (int) $row['failed_count'] : 0,
            isset($row['created_by']) ? (int) $row['created_by'] : null,
            (string) ($row['created_at'] ?? ''),
            isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            isset($row['sent_at']) ? (string) $row['sent_at'] : null,
        );
    }

    public function toRecord(): array
    {
        return [
            'subject'          => $this->subject,
            'body'             => $this->body,
            'status'           => $this->status,
            'recipients_count' => $this->recipientsCount,
            'sent_count'       => $this->sentCount,
            'failed_count'     => $this->failedCount,
            'created_by'       => $this->createdBy,
            'created_at'       => $this->createdAt,
            'updated_at'       => $this->updatedAt,
            'sent_at'          => $this->sentAt,
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

    public function subject(): string
    {
        return $this->subject;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function recipientsCount(): int
    {
        return $this->recipientsCount;
    }

    public function sentCount(): int
    {
        return $this->sentCount;
    }

    public function failedCount(): int
    {
        return $this->failedCount;
    }

    public function createdBy(): ?int
    {
        return $this->createdBy;
    }

    public function createdAt(): string
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function sentAt(): ?string
    {
        return $this->sentAt;
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->subject,
            $this->body,
            $this->status,
            $this->recipientsCount,
            $this->sentCount,
            $this->failedCount,
            $this->createdBy,
            $this->createdAt,
            $this->updatedAt,
            $this->sentAt,
        );
    }

    public function withStatus(string $status, ?string $sentAt = null): self
    {
        return new self(
            $this->id,
            $this->subject,
            $this->body,
            $status,
            $this->recipientsCount,
            $this->sentCount,
            $this->failedCount,
            $this->createdBy,
            $this->createdAt,
            $this->updatedAt,
            $sentAt,
        );
    }

    public function withCounts(int $sentCount, int $failedCount): self
    {
        return new self(
            $this->id,
            $this->subject,
            $this->body,
            $this->status,
            $this->recipientsCount,
            $sentCount,
            $failedCount,
            $this->createdBy,
            $this->createdAt,
            $this->updatedAt,
            $this->sentAt,
        );
    }

    public function withRecipientsCount(int $recipientsCount): self
    {
        return new self(
            $this->id,
            $this->subject,
            $this->body,
            $this->status,
            $recipientsCount,
            $this->sentCount,
            $this->failedCount,
            $this->createdBy,
            $this->createdAt,
            $this->updatedAt,
            $this->sentAt,
        );
    }

    public function withUpdatedAt(?string $updatedAt): self
    {
        return new self(
            $this->id,
            $this->subject,
            $this->body,
            $this->status,
            $this->recipientsCount,
            $this->sentCount,
            $this->failedCount,
            $this->createdBy,
            $this->createdAt,
            $updatedAt,
            $this->sentAt,
        );
    }
}
