<?php
// app/Support/OrderTimelineKeys.php

namespace App\Support;

final class OrderTimelineKeys
{
    public const CANCELED             = 'canceled';

    public const CREATED             = 'created';
    public const PUBLISHED           = 'published';
    public const ACCEPTED            = 'accepted';

    public const PICKUP_SCHEDULED    = 'pickup_scheduled';
    public const PICKED_UP           = 'picked_up';

    public const WEIGHT_REVIEWED     = 'weight_reviewed';
    public const WEIGHT_ACCEPTED     = 'weight_accepted';

    public const WASHING             = 'washing';
    public const READY               = 'ready';

    public const DELIVERY_SCHEDULED  = 'delivery_scheduled';
    public const OUT_FOR_DELIVERY    = 'out_for_delivery';
    public const DELIVERED           = 'delivered';

    public const COMPLETED           = 'completed';

    public static function all(): array
    {
        return [
            self::CANCELED,
            self::CREATED,
            self::PUBLISHED,
            self::ACCEPTED,

            self::PICKUP_SCHEDULED,
            self::PICKED_UP,

            self::WEIGHT_REVIEWED,
            self::WEIGHT_ACCEPTED,

            self::WASHING,
            self::READY,

            self::DELIVERY_SCHEDULED,
            self::OUT_FOR_DELIVERY,
            self::DELIVERED,

            self::COMPLETED,
        ];
    }
}
