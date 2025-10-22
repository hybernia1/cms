<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Services;

use Cms\Admin\Domain\Entities\NewsletterCampaign;
use Cms\Admin\Domain\Repositories\NewsletterCampaignRepository;
use Cms\Admin\Utils\DateTimeFactory;

final class NewsletterCampaignsService
{
    public function __construct(
        private readonly NewsletterCampaignRepository $repository = new NewsletterCampaignRepository(),
    ) {
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        return $this->repository->paginate($filters, $page, $perPage);
    }

    public function find(int $id): ?array
    {
        $campaign = $this->repository->find($id);
        return $campaign?->toArray() ?? null;
    }

    public function update(int $id, string $subject, string $body): array
    {
        $subject = trim($subject);
        $body = trim($body);

        if ($subject === '') {
            throw new \InvalidArgumentException('Vyplňte předmět kampaně.');
        }

        if ($body === '') {
            throw new \InvalidArgumentException('Vyplňte obsah kampaně.');
        }

        $campaign = $this->repository->find($id);
        if (!$campaign) {
            throw new \RuntimeException('Kampaň nebyla nalezena po aktualizaci.');
        }

        $now = DateTimeFactory::nowString();

        $updated = new NewsletterCampaign(
            $campaign->id(),
            $subject,
            $body,
            $campaign->status(),
            $campaign->recipientsCount(),
            $campaign->sentCount(),
            $campaign->failedCount(),
            $campaign->createdBy(),
            $campaign->createdAt(),
            $now,
            $campaign->sentAt(),
        );

        $campaign = $this->repository->update($updated);

        return $campaign->toArray();
    }

    public function duplicate(int $id, ?int $userId = null): array
    {
        $source = $this->repository->find($id);
        if (!$source) {
            throw new \RuntimeException('Kampaň nebyla nalezena.');
        }

        $duplicate = $this->repository->duplicate($source, $userId);

        return $duplicate->toArray();
    }

    public function delete(int $id): void
    {
        $this->repository->delete($id);
    }

    /**
     * @return array<int,array{id:int,name:string,email:?string}>
     */
    public function authors(): array
    {
        return $this->repository->authors();
    }
}

