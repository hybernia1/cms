<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Repositories;

use Cms\Admin\Domain\Entities\NewsletterCampaignSchedule;
use Core\Database\Init as DB;

final class NewsletterCampaignScheduleRepository
{
    /**
     * @param array<string,mixed> $where
     */
    private function updateWhere(array $where, array $data): void
    {
        $query = DB::query()
            ->table('newsletter_campaign_schedules')
            ->update($data);

        foreach ($where as $column => $value) {
            if ($value === null) {
                $query->whereNull($column);
            } else {
                $query->where($column, '=', $value);
            }
        }

        $query->execute();
    }

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

        $this->updateWhere(['id' => $id], $schedule->toRecord());

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

    public function findByCampaign(int $campaignId): ?NewsletterCampaignSchedule
    {
        $row = DB::query()
            ->table('newsletter_campaign_schedules')
            ->select(['*'])
            ->where('campaign_id', '=', $campaignId)
            ->first();

        return is_array($row) ? NewsletterCampaignSchedule::fromArray($row) : null;
    }

    /**
     * @param list<int> $campaignIds
     * @return array<int,NewsletterCampaignSchedule>
     */
    public function forCampaigns(array $campaignIds): array
    {
        $ids = array_values(array_unique(array_filter($campaignIds, static fn($value): bool => (int) $value > 0)));
        if ($ids === []) {
            return [];
        }

        $rows = DB::query()
            ->table('newsletter_campaign_schedules')
            ->select(['*'])
            ->whereIn('campaign_id', $ids)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $schedule = NewsletterCampaignSchedule::fromArray($row);
            $result[$schedule->campaignId()] = $schedule;
        }

        return $result;
    }

    /**
     * @return list<NewsletterCampaignSchedule>
     */
    public function dueSchedules(string $now, int $limit = 10): array
    {
        $query = DB::query()
            ->table('newsletter_campaign_schedules')
            ->select(['*'])
            ->where('status', '=', NewsletterCampaignSchedule::STATUS_SCHEDULED)
            ->where(static function ($where) use ($now): void {
                $where->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', $now);
            })
            ->where(static function ($where) use ($now): void {
                $where->whereNull('end_at')
                    ->orWhere('end_at', '>=', $now);
            })
            ->whereRaw('attempts < max_attempts')
            ->orderBy('next_run_at', 'ASC')
            ->orderBy('id', 'ASC');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $rows = $query->get();

        return array_map(static fn(array $row): NewsletterCampaignSchedule => NewsletterCampaignSchedule::fromArray($row), $rows);
    }

    public function deleteForCampaign(int $campaignId): void
    {
        DB::query()
            ->table('newsletter_campaign_schedules')
            ->delete()
            ->where('campaign_id', '=', $campaignId)
            ->execute();
    }
}
