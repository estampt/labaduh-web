<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ShopService;
use App\Models\VendorShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerDiscoveryServiceController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'lat' => ['required','numeric'],
            'lng' => ['required','numeric'],
            'radius_km' => ['nullable','integer','min:1','max:50'],
            'prioritize' => ['nullable','string','in:subscription,nearest,best_price,best_reviewed'],
        ]);

        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];
        $radiusKm = (int) ($data['radius_km'] ?? 3);
        $prioritize = $data['prioritize'] ?? 'subscription';

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Load candidate shops
        |--------------------------------------------------------------------------
        */
        $shops = VendorShop::query()
            ->where('vendor_shops.is_active', true)
            ->whereNotNull('vendor_shops.latitude')
            ->whereNotNull('vendor_shops.longitude')
            ->join('vendors', 'vendors.id', '=', 'vendor_shops.vendor_id')
            ->select([
                'vendor_shops.id',
                'vendor_shops.vendor_id',
                'vendor_shops.name',
                'vendor_shops.latitude',
                'vendor_shops.longitude',
                'vendors.subscription_tier',
            ])
            ->get();

        $rankedShops = collect();

        foreach ($shops as $shop) {

            $distanceKm = $this->distanceKm(
                $lat,
                $lng,
                (float) $shop->latitude,
                (float) $shop->longitude
            );

            if ($distanceKm > $radiusKm) continue;

            $rankedShops->push([
                'shop_id' => (int) $shop->id,
                'vendor_id' => (int) $shop->vendor_id,
                'name' => $shop->name,
                'subscription_tier' => $shop->subscription_tier,
                'subscription_score' => $this->subscriptionScore($shop->subscription_tier),
                'distance_km' => $distanceKm,

                'price_index' => null,
                'rating_avg' => null,
                'rating_count' => 0,
            ]);
        }

        if ($rankedShops->isEmpty()) {
            return response()->json(['data' => [], 'meta' => ['shops' => []]]);
        }

        $shopIds = $rankedShops->pluck('shop_id')->all();

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Price index (best price proxy)
        |--------------------------------------------------------------------------
        */
        $priceIndexByShop = ShopService::query()
            ->select('shop_id', DB::raw('AVG(COALESCE(min_price,0)) as price_index'))
            ->whereIn('shop_id', $shopIds)
            ->where('is_active', true)
            ->groupBy('shop_id')
            ->pluck('price_index', 'shop_id');

        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Ratings aggregation
        |--------------------------------------------------------------------------
        */
        $ratingsByShop = DB::table('order_feedbacks')
            ->select(
                'vendor_shop_id',
                DB::raw('AVG(rating) as rating_avg'),
                DB::raw('COUNT(*) as rating_count')
            )
            ->whereBetween('rating', [1, 5])
            ->whereIn('vendor_shop_id', $shopIds)
            ->groupBy('vendor_shop_id')
            ->get()
            ->keyBy('vendor_shop_id');

        $rankedShops = $rankedShops->map(function ($s) use ($priceIndexByShop, $ratingsByShop) {

            $shopId = (int) $s['shop_id'];

            $s['price_index'] = $priceIndexByShop[$shopId] ?? null;

            if (isset($ratingsByShop[$shopId])) {
                $row = $ratingsByShop[$shopId];
                $s['rating_avg'] = round((float) $row->rating_avg, 2);
                $s['rating_count'] = (int) $row->rating_count;
            }

            return $s;
        });

        /*
        |--------------------------------------------------------------------------
        | 4️⃣ Sort shop meta
        |--------------------------------------------------------------------------
        */
        $rankedShops = $this->sortShopMeta($rankedShops, $prioritize);

        /*
        |--------------------------------------------------------------------------
        | 5️⃣ Aggregate services + options
        |--------------------------------------------------------------------------
        */
        $services = [];

        $shopServices = ShopService::query()
            ->whereIn('shop_id', $shopIds)
            ->where('is_active', true)
            ->with([
                'service:id,name,icon,base_unit',
                'options' => function ($q) {
                        // filter pivot is_active (shop-specific)
                        $q->wherePivot('is_active', 1)
                          // (optional) also ensure option itself is active
                          ->where('service_options.is_active', 1)
                          ->orderBy('shop_service_options.sort_order');
                    },

            ])
            ->get();

        foreach ($shopServices as $ss) {

            $sid = (int) $ss->service_id;

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

                    '_addons' => [],
                    '_option_groups' => [],
                ];
            } else {
                $services[$sid]['base_price_min'] = min($services[$sid]['base_price_min'], $basePrice);
                $services[$sid]['base_price_max'] = max($services[$sid]['base_price_max'], $basePrice);

                $services[$sid]['excess_price_min'] = min($services[$sid]['excess_price_min'], $excessPrice);
                $services[$sid]['excess_price_max'] = max($services[$sid]['excess_price_max'], $excessPrice);
            }

            foreach ($ss->options as $opt) {

                $payload = [
                    'id' => (int) $opt->id,
                    'kind' => $opt->kind ?? 'addon',
                    'group_key' => $opt->group_key ?? null,
                    'name' => $opt->name ?? null,
                    'description' => $opt->description ?? null,

                    // price range: pivot override if present
                    'price_min' => (float) ($opt->pivot->price ?? $opt->price ?? 0),
                    'price_max' => (float) ($opt->pivot->price ?? $opt->price ?? 0),

                    // these should come from service_options table
                    'price_type' => (string) ($opt->price_type ?? 'fixed'),
                    'is_required' => (bool) ($opt->is_required ?? false),
                    'is_multi_select' => (bool) ($opt->is_multi_select ?? false),
                    'is_default_selected' => (bool) ($opt->is_default_selected ?? false),

                    // shop-level sort + active
                    'sort_order' => (int) ($opt->pivot->sort_order ?? $opt->sort_order ?? 0),
                    'is_active' => (bool) ($opt->pivot->is_active ?? $opt->is_active ?? true),
                ];


                if ($payload['kind'] === 'addon') {
                    $services[$sid]['_addons'] =
                        $this->mergeOptionRange($services[$sid]['_addons'], $payload);
                    continue;
                }

                $gk = $payload['group_key'] ?? 'general';

                if (!isset($services[$sid]['_option_groups'][$gk])) {
                    $services[$sid]['_option_groups'][$gk] = [
                        'group_key' => $gk,
                        'is_required' => false,
                        'is_multi_select' => false,
                        'items' => [],
                    ];
                }

                $services[$sid]['_option_groups'][$gk]['is_required'] |= $payload['is_required'];
                $services[$sid]['_option_groups'][$gk]['is_multi_select'] |= $payload['is_multi_select'];

                $services[$sid]['_option_groups'][$gk]['items'] =
                    $this->mergeOptionRange(
                        $services[$sid]['_option_groups'][$gk]['items'],
                        $payload
                    );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 6️⃣ Normalize output
        |--------------------------------------------------------------------------
        */
        foreach ($services as $sid => $svc) {

            $addons = array_values($svc['_addons']);
            $groups = [];

            foreach ($svc['_option_groups'] as $g) {
                $groups[] = [
                    'group_key' => $g['group_key'],
                    'is_required' => $g['is_required'],
                    'is_multi_select' => $g['is_multi_select'],
                    'items' => array_values($g['items']),
                ];
            }

            unset($services[$sid]['_addons'], $services[$sid]['_option_groups']);

            $services[$sid]['addons'] = $addons;
            $services[$sid]['option_groups'] = $groups;
        }

        return response()->json([
            'data' => array_values($services),
            'meta' => [
                'prioritize' => $prioritize,
                'shops' => $rankedShops->values(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function subscriptionScore(?string $tier): int
    {
        return match ($tier) {
            'premium' => 100,
            'standard' => 50,
            default => 10,
        };
    }

    private function sortShopMeta($shops, $prioritize)
    {
        return $shops->sort(function ($a, $b) use ($prioritize) {

            if ($prioritize === 'nearest') {
                return $a['distance_km'] <=> $b['distance_km'];
            }

            if ($prioritize === 'best_price') {
                return ($a['price_index'] ?? 999999)
                     <=> ($b['price_index'] ?? 999999);
            }

            if ($prioritize === 'best_reviewed') {
                return ($b['rating_avg'] ?? -1)
                     <=> ($a['rating_avg'] ?? -1);
            }

            return ($b['subscription_score'] ?? 0)
                 <=> ($a['subscription_score'] ?? 0);
        })->values();
    }

    private function mergeOptionRange(array $bucket, array $payload): array
    {
        $id = $payload['id'];

        if (!isset($bucket[$id])) {
            $bucket[$id] = $payload;
            return $bucket;
        }

        $bucket[$id]['price_min'] = min($bucket[$id]['price_min'], $payload['price_min']);
        $bucket[$id]['price_max'] = max($bucket[$id]['price_max'], $payload['price_max']);

        return $bucket;
    }

    private function distanceKm($lat1, $lng1, $lat2, $lng2): float
    {
        $earthKm = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat/2) ** 2 +
            cos(deg2rad($lat1)) *
            cos(deg2rad($lat2)) *
            sin($dLng/2) ** 2;

        return round($earthKm * 2 * atan2(sqrt($a), sqrt(1-$a)), 3);
    }
}
