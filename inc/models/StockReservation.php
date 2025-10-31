<?php
declare(strict_types=1);

namespace Cms\Models;

final class StockReservation extends BaseModel
{
    public const TABLE = 'stock_reservations';

    public const STATE_RESERVED = 'reserved';
    public const STATE_RELEASED = 'released';
    public const STATE_CONSUMED = 'consumed';
}
