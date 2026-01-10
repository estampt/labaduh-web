<?php

namespace App\Services;

class FulfillmentPricingService
{
    public function price(string $mode, float $distanceKm, array $vendorDeliveryRule = []): array
    {
        if ($mode === 'walk_in') {
            return [
                'delivery_fee' => 0.0,
                'source' => 'walk_in',
            ];
        }

        if ($mode === 'inhouse') {
            $base = (float) ($vendorDeliveryRule['base_fee'] ?? 0);
            $perKm = (float) ($vendorDeliveryRule['fee_per_km'] ?? 0);
            return [
                'delivery_fee' => round($base + ($distanceKm * $perKm), 2),
                'source' => 'vendor',
            ];
        }

        // third-party
        $estimated = max(0, $distanceKm) * 10; // placeholder estimate
        $markup = (float) config('fulfillment.third_party.markup_percent', 0);
        $fee = $estimated * (1 + ($markup / 100));

        return [
            'delivery_fee' => round($fee, 2),
            'source' => 'third_party_estimate',
            'provider' => config('fulfillment.third_party.default_provider'),
        ];
    }
}
