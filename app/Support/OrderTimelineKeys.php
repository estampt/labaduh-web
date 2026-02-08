<?php
// app/Support/OrderTimelineKeys.php

namespace App\Support;

final class OrderTimelineKeys
{
    public const ORDER_CREATED    = 'order_created';
    public const PUBLISHED        = 'published';
    public const PICKUP_SCHEDULED = 'pickup_scheduled';
    public const PICKED_UP        = 'picked_up';
    public const WASHING          = 'washing';
    public const READY            = 'ready';
    public const OUT_FOR_DELIVERY = 'out_for_delivery';
    public const DELIVERED        = 'delivered';
    public const COMPLETED        = 'completed';

    public static function all(): array
    {
        return [
            self::ORDER_CREATED,
            self::PUBLISHED,
            self::PICKUP_SCHEDULED,
            self::PICKED_UP,
            self::WASHING,
            self::READY,
            self::OUT_FOR_DELIVERY,
            self::DELIVERED,
            self::COMPLETED,
        ];
    }
}
