<?php

return [
    // System pricing fallback (used when vendor does not override)
    'min_kg_per_line' => 6.0,
    'rate_per_kg' => 8.0,

    // Delivery fallback
    'delivery_base_fee' => 0.0,
    'delivery_fee_per_km' => 2.5,

    // When vendor overrides are enabled
    'vendor_override_enabled' => true,
];
