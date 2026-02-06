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

    /**
     * Match job request to top vendor shops.
     *
     * Expected $job keys:
     * - pickup_date (Y-m-d)
     * - delivery_date (Y-m-d)
     * - pickup_time_start (HH:MM or HH:MM:SS)
     * - pickup_time_end
     * - delivery_time_start
     * - delivery_time_end
     * - pickup_lat, pickup_lng
     * - estimated_kg (float)
     * - items (optional; multi-line service kg etc.)
     */
    public function match(array $job, int $limit = 10): array
    {
        $pickupDow = (int) date('w', strtotime((string) ($job['pickup_date'] ?? '')));
        $deliveryDow = (int) date('w', strtotime((string) ($job['delivery_date'] ?? '')));

        $vendors = Vendor::query()
            ->where('status', 'approved')   // if your column is `status`, change this
            ->where('is_active', true)
            ->get();

        $candidates = [];

        foreach ($vendors as $vendor) {
            $shops = VendorShop::query()
                ->where('vendor_id', $vendor->id)
                ->where('is_active', true)
                ->get();

            foreach ($shops as $shop) {
                // Slot checks
                if (!$this->slots->isSlotAllowed(
                    $shop->id,
                    'pickup',
                    $pickupDow,
                    (string) ($job['pickup_time_start'] ?? ''),
                    (string) ($job['pickup_time_end'] ?? '')
                )) {
                    continue;
                }

                if (!$this->slots->isSlotAllowed(
                    $shop->id,
                    'delivery',
                    $deliveryDow,
                    (string) ($job['delivery_time_start'] ?? ''),
                    (string) ($job['delivery_time_end'] ?? '')
                )) {
                    continue;
                }

                // Capacity check
                $estimatedKg = (float) ($job['estimated_kg'] ?? 0);
                if (!$this->capacity->canReserve($shop, (string) $job['pickup_date'], $estimatedKg)) {
                    continue;
                }

                // Distance
                $distanceKm = $this->scoring->haversineKm(
                    (float) ($job['pickup_lat'] ?? 0),
                    (float) ($job['pickup_lng'] ?? 0),
                    (float) ($shop->latitude ?? 0),
                    (float) ($shop->longitude ?? 0),
                );

                // Radius filter
                if (!empty($shop->service_radius_km) && $distanceKm > (float) $shop->service_radius_km) {
                    continue;
                }

                // Capacity remaining + workload badge
                $cap = $this->capacity->remainingForDate($shop, (string) $job['pickup_date']);
                $workload = $this->scoring->workloadBadge(
                    (float) ($cap['remaining_kg'] ?? 0),
                    (float) ($cap['max_kg'] ?? 0)
                );

                // Pricing estimate
                $pricing = $this->scoring->estimatePricing(
                    (int) $vendor->id,
                    (int) $shop->id,
                    (float) $distanceKm,
                    (float) $estimatedKg,
                    $job['items'] ?? null
                );

                // Score
                $score = $this->scoring->score([
                    'vendor' => $vendor,
                    'shop' => $shop,
                    'distance_km' => $distanceKm,
                    'capacity' => $cap,
                    'pricing' => $pricing,
                ]);

                $candidates[] = [
                    'vendor_id' => (int) $vendor->id,
                    'shop_id' => (int) $shop->id,
                    'distance_km' => round($distanceKm, 2),
                    'capacity' => $cap,
                    'workload_badge' => $workload,
                    'pricing' => $pricing,
                    'score' => $score,
                    'vendor' => [
                        // use business_name if that’s what you store
                        'name' => (string) ($vendor->name ?? $vendor->business_name ?? ''),
                        'rating_avg' => (float) ($vendor->rating_avg ?? 0),
                        'rating_count' => (int) ($vendor->rating_count ?? 0),
                        'completed_orders_count' => (int) ($vendor->completed_orders_count ?? 0),
                        'unique_customers_served_count' => (int) ($vendor->unique_customers_served_count ?? 0),
                        'kilograms_processed_total' => (float) ($vendor->kilograms_processed_total ?? 0),
                    ],
                ];
            }
        }

        // Sort by score desc
        usort($candidates, function ($a, $b) {
            return ($b['score'] <=> $a['score']);
        });

        return array_slice($candidates, 0, $limit);
    }
}
