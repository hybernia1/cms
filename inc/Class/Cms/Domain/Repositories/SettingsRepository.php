<?php
declare(strict_types=1);

namespace Cms\Domain\Repositories;

use Core\Database\Init as DB;

final class SettingsRepository
{
    public function get(): array
    {
        $row = DB::query()->table('settings')->select(['*'])->where('id','=',1)->first();
        return $row ?? ['id'=>1,'site_title'=>'Můj web','site_email'=>'','data'=>null,'updated_at'=>null];
    }

    public function update(array $data): int
    {
        return DB::query()->table('settings')->update($data)->where('id','=',1)->execute();
    }
}
