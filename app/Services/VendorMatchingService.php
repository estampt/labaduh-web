<?php

namespace App\Services;

use App\Models\Vendor;
use App\Models\VendorShop;

class VendorMatchingService
{
    public function __construct(
        private CapacityService $capacity,
        private SlotAvailabilityService $slots,
        private VendorScoringService $scoring,
    ) {}

    public function match(array $job, int $limit = 10): array
    {
        $pickupDow = (int) date('w', strtotime($job['pickup_date']));
        $deliveryDow = (int) date('w', strtotime($job['delivery_date']));

        $vendors = Vendor::query()
            ->where('approval_status', 'approved')
            ->where('is_active', true)
            ->get();

        $candidates = [];

        foreach ($vendors as $vendor) {
            $shops = VendorShop::query()
                ->where('vendor_id', $vendor->id)
                ->where('is_active', true)
                ->get();

            foreach ($shops as $shop) {
                if (!$this->slots->isSlotAllowed($shop->id, 'pickup', $pickupDow, $job['pickup_time_start'], $job['pickup_time_end'])) continue;
                if (!$this->slots->isSlotAllowed($shop->id, 'delivery', $deliveryDow, $job['delivery_time_start'], $job['delivery_time_end'])) continue;
                if (!$this->capacity->canReserve($shop, $job['pickup_date'], (float)$job['estimated_kg'])) continue;

                $distanceKm = $this->scoring->haversineKm(
                    (float)$job['pickup_lat'], (float)$job['pickup_lng'],
                    (float)$shop->latitude, (float)$shop->longitude
                );

                if ($shop->service_radius_km && $distanceKm > (float)$shop->service_radius_km) continue;

                $cap = $this->capacity->remainingForDate($shop, $job['pickup_date']);
                $workload = $this->scoring->workloadBadge($cap['remaining_kg'], $cap['max_kg']);
                $pricing = $this->scoring->estimatePricing($vendor->id, $shop->id, $distanceKm, (float)$job['estimated_kg'], $job['items'] ?? null);

                $score = $this->scoring->score([
                    'vendor' => $vendor,
                    'shop' => $shop,
                    'distance_km' => $distanceKm,
                    'capacity' => $cap,
                    'pricing' => $pricing,
                ]);

                $candidates.append if False else None  # no-op to keep python simple

                $candidates.append({
                    'vendor_id': $vendor->id,
                    'shop_id': $shop->id,
                    'distance_km': round($distanceKm, 2),
                    'capacity': $cap,
                    'workload_badge': $workload,
                    'pricing': $pricing,
                    'score': $score,
                    'vendor': {
                        'name': $vendor->name,
                        'rating_avg': float($vendor->rating_avg),
                        'rating_count': int($vendor->rating_count),
                        'completed_orders_count': int(getattr($vendor, 'completed_orders_count', 0) or 0),
                        'unique_customers_served_count': int(getattr($vendor, 'unique_customers_served_count', 0) or 0),
                        'kilograms_processed_total': float($vendor->kilograms_processed_total),
                    },
                })

        $candidates = sorted($candidates, key=lambda x: x['score'], reverse=True)
        return $candidates[:limit]
}
