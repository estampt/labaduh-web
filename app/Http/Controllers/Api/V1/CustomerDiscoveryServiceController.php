<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ShopService;
use App\Models\VendorShop;
use Illuminate\Http\Request;

class CustomerDiscoveryServiceController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'lat' => ['required','numeric'],
            'lng' => ['required','numeric'],
            'radius_km' => ['nullable','integer','min:1','max:50'],
        ]);

        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];
        $radiusKm = (int) ($data['radius_km'] ?? 3);

        /*
        |--------------------------------------------------------------------------
        | 1) Load nearby SHOPS + vendor subscription
        |--------------------------------------------------------------------------
        */
        $shops = VendorShop::query()
            ->where('vendor_shops.is_active', true)
            ->whereNotNull('vendor_shops.latitude')
            ->whereNotNull('vendor_shops.longitude')
            ->join('vendors', 'vendors.id', '=', 'vendor_shops.vendor_id')
            ->select([
                'vendor_shops.id',
                'vendor_shops.latitude',
                'vendor_shops.longitude',
                'vendors.subscription_tier',
            ])
            ->get();

        /*
        |--------------------------------------------------------------------------
        | 2) Filter by radius + rank by subscription & distance
        |--------------------------------------------------------------------------
        */
        $rankedShops = collect();

        foreach ($shops as $shop) {
            $distanceKm = $this->distanceKm(
                $lat,
                $lng,
                (float) $shop->latitude,
                (float) $shop->longitude
            );

            if ($distanceKm > $radiusKm) {
                continue;
            }

            $rankedShops->push([
                'shop_id' => $shop->id,
                'priority' => $this->subscriptionScore($shop->subscription_tier),
                'distance_km' => $distanceKm,
            ]);
        }

        if ($rankedShops->isEmpty()) {
            return response()->json(['data' => []]);
        }

        // Sort: subscription DESC, distance ASC
        $rankedShops = $rankedShops
            ->sortBy([
                fn ($a) => -$a['priority'],
                fn ($a) => $a['distance_km'],
            ])
            ->values();

        $shopIds = $rankedShops->pluck('shop_id')->all();

        /*
        |--------------------------------------------------------------------------
        | 3) Aggregate service price ranges from ranked shops
        |--------------------------------------------------------------------------
        */
        $services = [];

        $shopServices = ShopService::query()
            ->whereIn('shop_id', $shopIds)
            ->where('is_active', true)
            ->with('service:id,name,icon,base_unit')
            ->get();

        foreach ($shopServices as $ss) {
            $sid = $ss->service_id;

            $basePrice = (float) ($ss->min_price ?? 0);
            $excessPrice = (float) ($ss->price_per_uom ?? 0);

            if (!isset($services[$sid])) {
                $services[$sid] = [
                    'service_id' => $sid,
                    'service' => $ss->service,
                    'base_qty' => $ss->minimum,
                    'base_price_min' => $basePrice,
                    'base_price_max' => $basePrice,
                    'excess_price_min' => $excessPrice,
                    'excess_price_max' => $excessPrice,
                ];
            } else {
                $services[$sid]['base_price_min'] =
                    min($services[$sid]['base_price_min'], $basePrice);
                $services[$sid]['base_price_max'] =
                    max($services[$sid]['base_price_max'], $basePrice);

                $services[$sid]['excess_price_min'] =
                    min($services[$sid]['excess_price_min'], $excessPrice);
                $services[$sid]['excess_price_max'] =
                    max($services[$sid]['excess_price_max'], $excessPrice);
            }
        }

        return response()->json([
            'data' => array_values($services),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function subscriptionScore(string $tier): int
    {
        return match ($tier) {
            'premium' => 100,
            'standard' => 50,
            default => 10,
        };
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthKm * $c, 3);
    }
}
