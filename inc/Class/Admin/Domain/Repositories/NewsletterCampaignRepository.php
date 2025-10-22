<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Repositories;

use Cms\Admin\Domain\Entities\NewsletterCampaign;
use Cms\Admin\Utils\DateTimeFactory;
use Core\Database\Init as DB;

final class NewsletterCampaignRepository
{
    public function create(NewsletterCampaign $campaign): NewsletterCampaign
    {
        $id = (int) DB::query()
            ->table('newsletter_campaigns')
            ->insert($campaign->toRecord())
            ->insertGetId();

        return $this->find($id) ?? $campaign->withId($id);
    }

    public function update(NewsletterCampaign $campaign): NewsletterCampaign
    {
        $id = $campaign->id();
        if ($id === null) {
            throw new \InvalidArgumentException('Campaign identifier is required for update.');
        }

        DB::query()
            ->table('newsletter_campaigns')
            ->update($campaign->toRecord())
            ->where('id', '=', $id)
            ->execute();

        return $this->find($id) ?? $campaign;
    }

    public function delete(int $id): void
    {
        DB::query()
            ->table('newsletter_campaigns')
            ->delete()
            ->where('id', '=', $id)
            ->execute();
    }

    public function find(int $id): ?NewsletterCampaign
    {
        $row = DB::query()
            ->table('newsletter_campaigns')
            ->select(['*'])
            ->where('id', '=', $id)
            ->first();

        return is_array($row) ? NewsletterCampaign::fromArray($row) : null;
    }

    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $query = DB::query()
            ->table('newsletter_campaigns', 'c')
            ->select([
                'c.*',
                'u.name AS author_name',
                'u.email AS author_email',
            ])
            ->leftJoin('users u', 'u.id', '=', 'c.created_by')
            ->orderBy('c.created_at', 'DESC');

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(static function ($where) use ($like): void {
                $where->whereLike('c.subject', $like)
                    ->orWhere('c.body', 'LIKE', $like);
            });
        }

        $author = isset($filters['author']) ? (int) $filters['author'] : 0;
        if ($author > 0) {
            $query->where('c.created_by', '=', $author);
        }

        return $query->paginate($page, $perPage);
    }

    public function duplicate(NewsletterCampaign $source, ?int $userId = null): NewsletterCampaign
    {
        $data = $source->toRecord();
        $data['created_by'] = $userId;
        $now = DateTimeFactory::nowString();
        $data['created_at'] = $now;
        $data['updated_at'] = $data['created_at'];
        $data['status'] = NewsletterCampaign::STATUS_DRAFT;
        $data['sent_at'] = null;
        $data['sent_count'] = 0;
        $data['failed_count'] = 0;

        $id = (int) DB::query()
            ->table('newsletter_campaigns')
            ->insert($data)
            ->insertGetId();

        return $this->find($id) ?? NewsletterCampaign::fromArray(array_merge($data, ['id' => $id]));
    }

    public function latest(): ?NewsletterCampaign
    {
        $row = DB::query()
            ->table('newsletter_campaigns')
            ->select(['*'])
            ->orderBy('created_at', 'DESC')
            ->limit(1)
            ->first();

        return is_array($row) ? NewsletterCampaign::fromArray($row) : null;
    }

    /**
     * @return array<int,array{id:int,name:string,email:?string}>
     */
    public function authors(): array
    {
        $rows = DB::query()
            ->table('newsletter_campaigns', 'c')
            ->select([
                'c.created_by AS id',
                'u.name AS name',
                'u.email AS email',
            ])
            ->leftJoin('users u', 'u.id', '=', 'c.created_by')
            ->groupBy('c.created_by', 'u.name', 'u.email')
            ->orderBy('u.name', 'ASC')
            ->get();

        $authors = [];
        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id <= 0) {
                continue;
            }

            $authors[] = [
                'id'    => $id,
                'name'  => (string) ($row['name'] ?? ''),
                'email' => isset($row['email']) ? (string) $row['email'] : null,
            ];
        }

        return $authors;
    }
}
