<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Services;

use Cms\Admin\Domain\Repositories\NewsletterSendsRepository;
use Cms\Admin\Utils\DateTimeFactory;

final class NewsletterCampaignsService
{
    public function __construct(
        private readonly NewsletterSendsRepository $repository = new NewsletterSendsRepository(),
    ) {
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        return $this->repository->paginate($filters, $page, $perPage);
    }

    public function find(int $id): ?array
    {
        return $this->repository->find($id);
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

        $this->repository->update($id, [
            'subject' => $subject,
            'body'    => $body,
        ]);

        $campaign = $this->repository->find($id);
        if (!$campaign) {
            throw new \RuntimeException('Kampaň nebyla nalezena po aktualizaci.');
        }

        return $campaign;
    }

    public function duplicate(int $id, ?int $userId = null): array
    {
        $source = $this->repository->find($id);
        if (!$source) {
            throw new \RuntimeException('Kampaň nebyla nalezena.');
        }

        $newId = $this->repository->duplicate([
            'subject'          => (string) ($source['subject'] ?? ''),
            'body'             => (string) ($source['body'] ?? ''),
            'recipients_count' => 0,
            'sent_count'       => 0,
            'failed_count'     => 0,
            'created_by'       => $userId,
            'created_at'       => DateTimeFactory::nowString(),
        ]);

        $campaign = $this->repository->find($newId);
        if (!$campaign) {
            throw new \RuntimeException('Nová kampaň nebyla nalezena.');
        }

        return $campaign;
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

