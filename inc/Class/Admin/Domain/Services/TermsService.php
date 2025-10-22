<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Services;

use Cms\Admin\Domain\Repositories\TermsRepository;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Utils\Slugger;
use Core\Validation\Validator;

final class TermsService
{
    public function __construct(private readonly TermsRepository $repo = new TermsRepository()) {}

    public function create(string $name, string $type = 'tag', ?string $slug = null, ?string $description = null): int
    {
        $v = (new Validator())->require(compact('name'), 'name');
        if (!$v->ok()) throw new \InvalidArgumentException(json_encode($v->errors(), JSON_UNESCAPED_UNICODE));

        $baseSlug = trim((string)($slug ?? ''));
        $slug = $baseSlug !== ''
            ? Slugger::uniqueInTerms($baseSlug, $type)
            : Slugger::uniqueInTerms($name, $type);

        return $this->repo->create([
            'type'        => $type,
            'slug'        => $slug,
            'name'        => $name,
            'description' => $description,
            'created_at'  => DateTimeFactory::nowString(),
        ]);
    }
}
