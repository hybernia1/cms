<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Repositories;

use Cms\Admin\Domain\Entities\NewsletterCampaignLog;
use Core\Database\Init as DB;

final class NewsletterCampaignLogRepository
{
    public function create(NewsletterCampaignLog $log): NewsletterCampaignLog
    {
        $id = (int) DB::query()
            ->table('newsletter_campaign_logs')
            ->insert($log->toRecord())
            ->insertGetId();

        return $this->find($id) ?? $log->withId($id);
    }

    public function update(NewsletterCampaignLog $log): NewsletterCampaignLog
    {
        $id = $log->id();
        if ($id === null) {
            throw new \InvalidArgumentException('Log identifier is required for update.');
        }

        DB::query()
            ->table('newsletter_campaign_logs')
            ->update($log->toRecord())
            ->where('id', '=', $id)
            ->execute();

        return $this->find($id) ?? $log;
    }

    public function find(int $id): ?NewsletterCampaignLog
    {
        $row = DB::query()
            ->table('newsletter_campaign_logs')
            ->select(['*'])
            ->where('id', '=', $id)
            ->first();

        return is_array($row) ? NewsletterCampaignLog::fromArray($row) : null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function forCampaign(int $campaignId): array
    {
        return DB::query()
            ->table('newsletter_campaign_logs')
            ->select(['*'])
            ->where('campaign_id', '=', $campaignId)
            ->orderBy('id', 'ASC')
            ->get();
    }
}
