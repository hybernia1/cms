<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Services;

use Cms\Admin\Domain\Entities\NewsletterCampaign;
use Cms\Admin\Domain\Entities\NewsletterCampaignSchedule;
use Cms\Admin\Domain\Repositories\NewsletterCampaignRepository;
use Cms\Admin\Domain\Repositories\NewsletterCampaignScheduleRepository;
use Cms\Admin\Utils\DateTimeFactory;

final class NewsletterCampaignsService
{
    public function __construct(
        private readonly NewsletterCampaignRepository $repository = new NewsletterCampaignRepository(),
        private readonly NewsletterCampaignScheduleRepository $scheduleRepository = new NewsletterCampaignScheduleRepository(),
    ) {
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $paginated = $this->repository->paginate($filters, $page, $perPage);
        $items = $paginated['items'] ?? [];

        if ($items !== []) {
            $campaignIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $items);
            $schedules = $this->scheduleRepository->forCampaigns($campaignIds);

            foreach ($items as &$item) {
                $id = (int) ($item['id'] ?? 0);
                $schedule = $schedules[$id] ?? null;
                $item['schedule'] = $schedule?->toArray();
            }
            unset($item);

            $paginated['items'] = $items;
        }

        return $paginated;
    }

    public function find(int $id): ?array
    {
        $campaign = $this->repository->find($id);
        if (!$campaign) {
            return null;
        }

        $data = $campaign->toArray();
        $schedule = $this->scheduleRepository->findByCampaign($campaign->id() ?? 0);
        if ($schedule) {
            $data['schedule'] = $schedule->toArray();
        }

        return $data;
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

    public function configureSchedule(
        int $campaignId,
        ?string $startAt,
        ?string $endAt,
        int $intervalMinutes,
        int $maxAttempts
    ): array {
        $campaign = $this->repository->find($campaignId);
        if (!$campaign) {
            throw new \RuntimeException('Kampaň nebyla nalezena.');
        }

        $intervalMinutes = max(0, $intervalMinutes);
        $maxAttempts = max(1, $maxAttempts);

        $start = DateTimeFactory::fromUserInput($startAt);
        $end = DateTimeFactory::fromUserInput($endAt);

        if ($start !== null && $end !== null && $end < $start) {
            throw new \InvalidArgumentException('Datum ukončení musí následovat po datu začátku.');
        }

        $now = DateTimeFactory::now();
        $start = $start ?? $now;
        $startString = DateTimeFactory::formatForStorage($start);
        $endString = $end ? DateTimeFactory::formatForStorage($end) : null;

        $nextRunAt = $start <= $now ? DateTimeFactory::nowString() : $startString;

        if ($end !== null) {
            $nextRunDate = DateTimeFactory::fromStorage($nextRunAt);
            if ($nextRunDate !== null && $nextRunDate > $end) {
                $nextRunAt = DateTimeFactory::formatForStorage($end);
            }
        }

        $schedule = $this->scheduleRepository->findByCampaign($campaignId);

        $base = new NewsletterCampaignSchedule(
            $schedule?->id(),
            $campaign->id() ?? 0,
            NewsletterCampaignSchedule::STATUS_SCHEDULED,
            $startString,
            $endString,
            $intervalMinutes,
            $maxAttempts,
            0,
            $nextRunAt,
            null,
            $schedule?->createdAt() ?? DateTimeFactory::nowString(),
            DateTimeFactory::nowString(),
        );

        if ($schedule === null) {
            $schedule = $this->scheduleRepository->create($base);
        } else {
            $schedule = $this->scheduleRepository->update($base);
        }

        $this->touchCampaignStatus($campaign, NewsletterCampaign::STATUS_SCHEDULED);

        return $schedule->toArray();
    }

    public function pauseSchedule(int $campaignId): array
    {
        $schedule = $this->scheduleRepository->findByCampaign($campaignId);
        if ($schedule === null) {
            throw new \RuntimeException('Kampaň nemá nastavený plán.');
        }

        if ($schedule->status() === NewsletterCampaignSchedule::STATUS_COMPLETED) {
            throw new \RuntimeException('Nelze pozastavit dokončený plán.');
        }

        $updated = new NewsletterCampaignSchedule(
            $schedule->id(),
            $schedule->campaignId(),
            NewsletterCampaignSchedule::STATUS_PAUSED,
            $schedule->startAt(),
            $schedule->endAt(),
            $schedule->intervalMinutes(),
            $schedule->maxAttempts(),
            $schedule->attempts(),
            $schedule->nextRunAt(),
            $schedule->lastRunAt(),
            $schedule->createdAt(),
            DateTimeFactory::nowString(),
        );

        $schedule = $this->scheduleRepository->update($updated);

        return $schedule->toArray();
    }

    public function resumeSchedule(int $campaignId): array
    {
        $schedule = $this->scheduleRepository->findByCampaign($campaignId);
        if ($schedule === null) {
            throw new \RuntimeException('Kampaň nemá nastavený plán.');
        }

        if ($schedule->status() === NewsletterCampaignSchedule::STATUS_COMPLETED) {
            throw new \RuntimeException('Dokončený plán nelze znovu spustit, vytvořte nový.');
        }

        if ($schedule->attempts() >= $schedule->maxAttempts()) {
            throw new \RuntimeException('Byl dosažen maximální počet pokusů.');
        }

        $nowString = DateTimeFactory::nowString();
        $nextRunAt = $schedule->nextRunAt();
        if ($nextRunAt === null) {
            $nextRunAt = $nowString;
        }

        $updated = new NewsletterCampaignSchedule(
            $schedule->id(),
            $schedule->campaignId(),
            NewsletterCampaignSchedule::STATUS_SCHEDULED,
            $schedule->startAt(),
            $schedule->endAt(),
            $schedule->intervalMinutes(),
            $schedule->maxAttempts(),
            $schedule->attempts(),
            $nextRunAt,
            $schedule->lastRunAt(),
            $schedule->createdAt(),
            $nowString,
        );

        $schedule = $this->scheduleRepository->update($updated);

        $campaign = $this->repository->find($campaignId);
        if ($campaign) {
            $this->touchCampaignStatus($campaign, NewsletterCampaign::STATUS_SCHEDULED);
        }

        return $schedule->toArray();
    }

    public function triggerNow(int $campaignId): array
    {
        $schedule = $this->scheduleRepository->findByCampaign($campaignId);
        if ($schedule === null) {
            throw new \RuntimeException('Kampaň nemá nastavený plán.');
        }

        if ($schedule->attempts() >= $schedule->maxAttempts()) {
            throw new \RuntimeException('Byl dosažen maximální počet pokusů.');
        }

        $nowString = DateTimeFactory::nowString();

        $updated = new NewsletterCampaignSchedule(
            $schedule->id(),
            $schedule->campaignId(),
            NewsletterCampaignSchedule::STATUS_SCHEDULED,
            $schedule->startAt(),
            $schedule->endAt(),
            $schedule->intervalMinutes(),
            $schedule->maxAttempts(),
            $schedule->attempts(),
            $nowString,
            $schedule->lastRunAt(),
            $schedule->createdAt(),
            $nowString,
        );

        $schedule = $this->scheduleRepository->update($updated);

        $campaign = $this->repository->find($campaignId);
        if ($campaign) {
            $this->touchCampaignStatus($campaign, NewsletterCampaign::STATUS_SCHEDULED);
        }

        return $schedule->toArray();
    }

    private function touchCampaignStatus(NewsletterCampaign $campaign, string $status): void
    {
        if ($campaign->status() === $status) {
            return;
        }

        $updated = new NewsletterCampaign(
            $campaign->id(),
            $campaign->subject(),
            $campaign->body(),
            $status,
            $campaign->recipientsCount(),
            $campaign->sentCount(),
            $campaign->failedCount(),
            $campaign->createdBy(),
            $campaign->createdAt(),
            DateTimeFactory::nowString(),
            $campaign->sentAt(),
        );

        $this->repository->update($updated);
    }
}

