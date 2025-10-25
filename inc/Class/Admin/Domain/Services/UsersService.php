<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Services;

use Cms\Admin\Auth\Passwords;
use Cms\Admin\Domain\Repositories\UsersRepository;
use Cms\Admin\Utils\DateTimeFactory;
use Core\Validation\Validator;

final class UsersService
{
    public function __construct(private readonly UsersRepository $repo = new UsersRepository()) {}

    public function createAdmin(string $name, string $email, string $password): int
    {
        $v = (new Validator())
            ->require(compact('name'), 'name')
            ->require(compact('email'), 'email')->email(compact('email'), 'email')
            ->minLen(compact('password'), 'password', 8);

        if (!$v->ok()) {
            throw new \InvalidArgumentException(json_encode($v->errors(), JSON_UNESCAPED_UNICODE));
        }

        $slugService = new UserSlugService($this->repo);
        $slug = $slugService->generate($name);

        $hash = Passwords::hash($password);
        return $this->repo->create([
            'name'          => $name,
            'slug'          => $slug,
            'email'         => $email,
            'password_hash' => $hash,
            'active'        => 1,
            'role'          => 'admin',
            'created_at'    => DateTimeFactory::nowString(),
            'updated_at'    => null,
        ]);
    }
}
