<?php
declare(strict_types=1);

namespace Cms\Models\Repositories;

use Cms\Models\ProductAttribute;

final class ProductAttributeRepository extends BaseRepository
{
    protected function table(): string
    {
        return ProductAttribute::TABLE;
    }

    protected function modelClass(): string
    {
        return ProductAttribute::class;
    }
}
