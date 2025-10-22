<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Repositories;

use Cms\Admin\Domain\Entities\NewsletterCampaignSchedule;
use Core\Database\Init as DB;

final class NewsletterCampaignScheduleRepository
{
    public function create(NewsletterCampaignSchedule $schedule): NewsletterCampaignSchedule
    {
        $id = (int) DB::query()
            ->table('newsletter_campaign_schedules')
            ->insert($schedule->toRecord())
            ->insertGetId();

        return $this->find($id) ?? $schedule->withId($id);
    }

    public function update(NewsletterCampaignSchedule $schedule): NewsletterCampaignSchedule
    {
        $id = $schedule->id();
        if ($id === null) {
            throw new \InvalidArgumentException('Schedule identifier is required for update.');
        }

        DB::query()
            ->table('newsletter_campaign_schedules')
            ->update($schedule->toRecord())
            ->where('id', '=', $id)
            ->execute();

        return $this->find($id) ?? $schedule;
    }

    public function find(int $id): ?NewsletterCampaignSchedule
    {
        $row = DB::query()
            ->table('newsletter_campaign_schedules')
            ->select(['*'])
            ->where('id', '=', $id)
            ->first();

        return is_array($row) ? NewsletterCampaignSchedule::fromArray($row) : null;
    }

    public function markProcessed(int $id, string $status, ?string $processedAt = null): void
    {
        DB::query()
            ->table('newsletter_campaign_schedules')
            ->update([
                'status'       => $status,
                'processed_at' => $processedAt,
            ])
            ->where('id', '=', $id)
            ->execute();
    }
}
