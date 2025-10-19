<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Services;

use Cms\Admin\Domain\Repositories\TermsRepository;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Utils\Slugger;
use Cms\Admin\Validation\Validator;

final class TermsService
{
    public function __construct(private readonly TermsRepository $repo = new TermsRepository()) {}

    public function create(string $name, string $type = 'tag', ?string $slug = null, ?string $description = null): int
    {
        $v = (new Validator())->require(compact('name'), 'name');
        if (!$v->ok()) throw new \InvalidArgumentException(json_encode($v->errors(), JSON_UNESCAPED_UNICODE));

        $slug = $slug ?: Slugger::make($name);
        // unikatnost slugů přes existující repo (není per-type unikátní; pokud chceš, můžeš přidat kontrolu per type)
        if ($this->repo->findBySlug($slug)) {
            $slug = $slug . '-' . substr(bin2hex(random_bytes(2)),0,3);
        }

        return $this->repo->create([
            'type'        => $type,
            'slug'        => $slug,
            'name'        => $name,
            'description' => $description,
            'created_at'  => DateTimeFactory::nowString(),
        ]);
    }
}
