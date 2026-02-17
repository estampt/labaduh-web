<?php

return [
    'order_enabled' => env('CHAT_ORDER_ENABLED', true),
    'shop_enabled'  => env('CHAT_SHOP_ENABLED', false),

    // lock chat when order reaches these statuses:
    'order_locked_statuses' => ['completed', 'cancelled'],

    /*
    //TODO: to set up order statues
    $lockedStatuses = config('chat.order_locked_statuses', []);
        if (in_array($order->status, $lockedStatuses, true)) {
           // lock
        }

    */
];
