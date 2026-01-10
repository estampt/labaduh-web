<?php

return [
    'subscription_tiers' => [
        'free' => [
            'score_boost_percent' => 0,
            'service_fee_percent' => 10,
            'early_notification_seconds' => 60,
        ],
        'pro' => [
            'score_boost_percent' => 15,
            'service_fee_percent' => 7,
            'early_notification_seconds' => 30,
        ],
        'elite' => [
            'score_boost_percent' => 30,
            'service_fee_percent' => 5,
            'early_notification_seconds' => 0,
        ],
    ],
];
