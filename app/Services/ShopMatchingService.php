<?php

namespace App\Services;

use App\Models\VendorShop;
use App\Models\Order;
use Illuminate\Support\Collection;


use Illuminate\Support\Facades\DB;

class ShopMatchingService
{
    /**
     * Very practical matching: return ranked candidate shops.
     * Inputs:
     * - customer_lat, customer_lng
     * - required_kg (sum)
     * - pickup_date (Y-m-d) optional
     */
    public function match(array $payload, int $limit = 10): array
    {
        $lat = (float) ($payload['customer_lat'] ?? 0);
        $lng = (float) ($payload['customer_lng'] ?? 0);
        $requiredKg = (float) ($payload['required_kg'] ?? 0);
        $pickupDate = $payload['pickup_date'] ?? null;

        // Basic Haversine in SQL (km)
        $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(vendor_shops.latitude)) * cos(radians(vendor_shops.longitude) - radians(?)) + sin(radians(?)) * sin(radians(vendor_shops.latitude))))";

        $query = VendorShop::query()
            ->select('vendor_shops.*')
            ->selectRaw($haversine . ' AS distance_km', [$lat, $lng, $lat])
            ->where('vendor_shops.is_active', true)
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('vendors')
                    ->whereColumn('vendors.id', 'vendor_shops.vendor_id')
                    ->whereIn('vendors.status', ['approved']); // adjust if your enum differs
            });

        // Capacity constraint (optional if you create capacity rows)
        if ($pickupDate) {
            $query->leftJoin('shop_capacities as sc', function ($join) use ($pickupDate) {
                $join->on('sc.shop_id', '=', 'vendor_shops.id')
                    ->where('sc.date', '=', $pickupDate);
            });

            // If capacity row exists, enforce it; otherwise allow (vendor has no capacity config yet)
            $query->where(function ($q) use ($requiredKg) {
                $q->whereNull('sc.id')
                  ->orWhere(function ($q2) use ($requiredKg) {
                      $q2->where(function ($qq) use ($requiredKg) {
                          $qq->whereNull('sc.max_kg')
                             ->orWhereRaw('(sc.max_kg - sc.booked_kg) >= ?', [$requiredKg]);
                      })
                      ->where(function ($qq) {
                          $qq->whereNull('sc.max_orders')
                             ->orWhereRaw('(sc.max_orders - sc.booked_orders) >= 1');
                      });
                  });
            });
        }

        // TODO: add rating/subscription ranking once those are in tables.
        $query->orderBy('distance_km', 'asc')
              ->limit($limit);

        return $query->get()->map(function ($shop) {
            return [
                'shop_id' => $shop->id,
                'vendor_id' => $shop->vendor_id,
                'name' => $shop->name,
                'address_line' => $shop->address_line,
                'latitude' => (float) $shop->latitude,
                'longitude' => (float) $shop->longitude,
                'distance_km' => round((float) $shop->distance_km, 2),
            ];
        })->all();
    }


    public function matchForOrderBroadcast(Order $order, int $limit = 50): array
    {
        $lat = (float) $order->search_lat;
        $lng = (float) $order->search_lng;
        $radiusKm = (float) ($order->radius_km ?? 3);

        // 1) required services from order items
        $requiredServiceIds = $order->items()
            ->select('service_id')
            ->distinct()
            ->pluck('service_id')
            ->map(fn ($v) => (int) $v)
            ->values();

        if ($requiredServiceIds->isEmpty()) return [];

        // 2) SQL distance (km) — keep your current haversine style
        $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(vendor_shops.latitude)) * cos(radians(vendor_shops.longitude) - radians(?)) + sin(radians(?)) * sin(radians(vendor_shops.latitude))))";

        // 3) base query: nearby + approved vendor + join subscription
        $q = VendorShop::query()
            ->select('vendor_shops.*')
            ->join('vendors', 'vendors.id', '=', 'vendor_shops.vendor_id')
            ->selectRaw($haversine . ' AS distance_km', [$lat, $lng, $lat])
            ->addSelect('vendors.subscription_tier')
            ->where('vendor_shops.is_active', true)
            ->whereNotNull('vendor_shops.latitude')
            ->whereNotNull('vendor_shops.longitude')
            // approved vendor (keep your current logic)
            ->whereIn('vendors.status', ['approved'])
            // radius filter
            ->havingRaw('distance_km <= ?', [$radiusKm]);

        // 4) capability filter: shop must have ALL required services active
        $requiredCount = $requiredServiceIds->count();

        $q->whereIn('vendor_shops.id', function ($sub) use ($requiredServiceIds, $requiredCount) {
            $sub->select('shop_id')
                ->from('shop_services')
                ->where('is_active', 1)
                ->whereIn('service_id', $requiredServiceIds->all())
                ->groupBy('shop_id')
                ->havingRaw('COUNT(DISTINCT service_id) = ?', [$requiredCount]);
        });

        // 5) subscription ranking (DESC), distance (ASC)
        // We'll compute a numeric score in SQL using CASE
        $q->orderByRaw("
            CASE vendors.subscription_tier
                WHEN 'premium' THEN 100
                WHEN 'standard' THEN 50
                ELSE 10
            END DESC
        ");
        $q->orderBy('distance_km', 'asc');

        $q->limit($limit);

        return $q->get()->map(function ($shop) {
            $tier = (string) ($shop->subscription_tier ?? 'free');

            return [
                'shop_id' => (int) $shop->id,
                'vendor_id' => (int) $shop->vendor_id,
                'name' => $shop->name,
                'address_line' => $shop->address_line,
                'latitude' => (float) $shop->latitude,
                'longitude' => (float) $shop->longitude,
                'distance_km' => round((float) $shop->distance_km, 2),
                'subscription_tier' => $tier,
                'priority_score' => $this->subscriptionScore($tier),
            ];
        })->all();
    }

    private function subscriptionScore(string $tier): int
    {
        return match ($tier) {
            'premium' => 100,
            'standard' => 50,
            default => 10,
        };
    }

}
