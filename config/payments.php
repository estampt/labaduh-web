<?php

return [
    'provider' => env('PAYMENT_PROVIDER', 'paymongo'),

    // PayMongo
    'paymongo' => [
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
        'webhook_signature' => env('PAYMONGO_WEBHOOK_SIGNATURE'), // optional if you implement signature validation
        'base_url' => env('PAYMONGO_BASE_URL', 'https://api.paymongo.com/v1'),
    ],

    // Payment behavior
    'order_payment' => [
        'currency' => 'PHP',
        'require_payment_before_broadcast' => false, // set true if you want pay-first then matching
    ],
];
