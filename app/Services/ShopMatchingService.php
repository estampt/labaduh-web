<?php

namespace App\Services;

use App\Models\VendorShop;
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
}
