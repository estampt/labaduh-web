<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderBroadcast;
use App\Models\VendorShop;

use App\Support\OrderTimelineKeys;
use App\Services\OrderTimelineRecorder;
use App\Services\OrderTimelineService;

use App\Services\OrderBroadcastService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerOrderController extends Controller
{

    public function store(Request $request)
    {


        $data = $request->validate([
            'search_lat' => ['required','numeric'],
            'search_lng' => ['required','numeric'],
            'radius_km' => ['nullable','integer','min:1','max:50'],

            'pickup_mode' => ['required','in:asap,tomorrow,schedule'],
            'pickup_window_start' => ['nullable','date'],
            'pickup_window_end' => ['nullable','date'],

            'delivery_mode' => ['required','in:pickup_deliver,walk_in'],

            'pickup_address_id' => ['nullable','integer'],
            'delivery_address_id' => ['nullable','integer'],
            'pickup_address_snapshot' => ['nullable','array'],
            'delivery_address_snapshot' => ['nullable','array'],

            // ✅ FIX: remove invalid rules that were causing foreach(null) crash
            // pickup_provider / delivery_provider / pickup_driver_id / delivery_driver_id
            // will be set as defaults when creating the order.

            'currency' => ['nullable','string','size:3'],
            'notes' => ['nullable','string'],

            'items' => ['required','array','min:1'],
            'items.*.service_id' => ['required','integer','exists:services,id'],
            'items.*.qty' => ['required','numeric','min:0.01'],
            'items.*.uom' => ['nullable','string','max:16'],

            // snapshot pricing
            'items.*.pricing_model' => ['nullable','string','max:32'],
            'items.*.minimum' => ['nullable','numeric'],
            'items.*.min_price' => ['nullable','numeric'],
            'items.*.price_per_uom' => ['nullable','numeric'],
            'items.*.computed_price' => ['required','numeric','min:0'],

            'items.*.options' => ['nullable','array'],
            'items.*.options.*.service_option_id' => ['required','integer','exists:service_options,id'],
            'items.*.options.*.price' => ['required','numeric','min:0'],
            'items.*.options.*.is_required' => ['nullable','boolean'],
            'items.*.options.*.computed_price' => ['nullable','numeric','min:0'],
        ]);



        $customerId = (int) auth()->id();

        $order = DB::transaction(function () use ($data, $customerId) {
            $settings = app(\App\Services\AppSettings::class);
            $minRadiusKm = (float) $settings->get('broadcast.min_radius_km', 20.0);
            $radiusKm = max((float) ($data['radius_km'] ?? 3), $minRadiusKm);

            $order = Order::create([
                'customer_id' => $customerId,
                'status' => 'published',
                'search_lat' => $data['search_lat'],
                'search_lng' => $data['search_lng'],
                'radius_km' => $radiusKm,
                'pickup_mode' => $data['pickup_mode'],
                'pickup_window_start' => $data['pickup_window_start'] ?? null,
                'pickup_window_end' => $data['pickup_window_end'] ?? null,
                'delivery_mode' => $data['delivery_mode'],
                'pickup_address_id' => $data['pickup_address_id'] ?? null,
                'delivery_address_id' => $data['delivery_address_id'] ?? null,
                'pickup_address_snapshot' => $data['pickup_address_snapshot'] ?? null,
                'delivery_address_snapshot' => $data['delivery_address_snapshot'] ?? null,

                // ✅ FIX: set defaults here (instead of invalid validation rules)
                'pickup_provider' => 'vendor',
                'delivery_provider' => 'vendor',
                'pickup_driver_id' => null,
                'delivery_driver_id' => null,

                'currency' => $data['currency'] ?? 'SGD',
                'notes' => $data['notes'] ?? null,
            ]);

            $subtotal = 0;

            foreach ($data['items'] as $it) {
                $item = $order->items()->create([
                    'service_id' => $it['service_id'],
                    'qty' => $it['qty'],
                    'uom' => $it['uom'] ?? null,
                    'pricing_model' => $it['pricing_model'] ?? null,
                    'minimum' => $it['minimum'] ?? null,
                    'min_price' => $it['min_price'] ?? null,
                    'price_per_uom' => $it['price_per_uom'] ?? null,
                    'computed_price' => $it['computed_price'],
                ]);

                $subtotal += (float) $it['computed_price'];

                foreach (($it['options'] ?? []) as $opt) {
                    $item->options()->create([
                        'service_option_id' => $opt['service_option_id'],
                        'price' => $opt['price'],
                        'is_required' => (bool) ($opt['is_required'] ?? false),
                        'computed_price' => $opt['computed_price'] ?? $opt['price'],
                    ]);

                    $subtotal += (float) ($opt['computed_price'] ?? $opt['price']);
                }
            }

            // Placeholder fees (replace later)
            $deliveryFee = 49.00;
            $serviceFee = 15.00;
            $discount = 0;

            $total = round($subtotal + $deliveryFee + $serviceFee - $discount, 2);

            $order->update([
                'subtotal' => round($subtotal, 2),
                'delivery_fee' => $deliveryFee,
                'service_fee' => $serviceFee,
                'discount' => $discount,
                'total' => $total,
            ]);


            // ✅ Broadcast to shops within radius (create order_broadcasts rows)
            $this->broadcastToNearbyShops($order);

            app(OrderTimelineRecorder::class)->record(
                $order,
                OrderTimelineKeys::ORDER_CREATED,
                'customer',
                auth()->id()
            );
            return $order;
        });


        return response()->json([
            'data' => $order->load('items.options'),
        ]);
    }

    /*
    private function broadcastToNearbyShops(\App\Models\Order $order): void
    {
        $lat = (float) $order->search_lat;
        $lng = (float) $order->search_lng;
        $radiusKm = (float) ($order->radius_km ?? 3);

        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / (111.0 * max(cos(deg2rad($lat)), 0.01));

        $shops = \App\Models\VendorShop::query()
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta])
            ->get(['id', 'vendor_id', 'latitude', 'longitude']);

        if ($shops->isEmpty()) return;

        foreach ($shops as $shop) {
            if (!$this->withinRadiusKm(
                $lat,
                $lng,
                (float) $shop->latitude,
                (float) $shop->longitude,
                $radiusKm
            )) {
                continue;
            }

            \App\Models\OrderBroadcast::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'shop_id'  => $shop->id,
                ],
                [
                    'vendor_id' => $shop->vendor_id,
                    'status'    => 'pending',
                ]
            );
        }
    }

    */
    private function withinRadiusKm(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2,
        float $radiusKm
    ): bool {
        $earthKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return ($earthKm * $c) <= $radiusKm;
    }

    public function show(Order $order)
    {
        abort_unless((int) $order->customer_id === (int) auth()->id(), 403);

        // Load items + timeline events (Option B)
        $order->load(['items.options', 'timelineEvents']);

        $timeline = app(OrderTimelineService::class)->forCustomer($order);

        return response()->json([
            'data' => [
                'order' => $order,
                // keep old key name for frontend compatibility
                'timeline' => $timeline,
            ],
        ]);
    }
    //TODO : OPtion for later
/*
    public function show(Order $order)
    {
        abort_unless((int) $order->customer_id === (int) auth()->id(), 403);

        $order->load(['items.options', 'timelineEvents']);

        $structured = app(OrderTimelineService::class)->forCustomer($order);

        // Convert structured steps to old-style array of completed keys
        $timelineRaw = collect($structured['steps'])
            ->filter(fn($s) => in_array($s['state'], ['done', 'current']))
            ->pluck('key')
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'order' => $order,
                'timeline_raw' => $timelineRaw,
                'timeline' => $structured,
            ],
        ]);
    }

*/

    /*
    private function broadcastToNearbyShops(\App\Models\Order $order): void
    {
        $lat = (float) $order->search_lat;
        $lng = (float) $order->search_lng;
        $radiusKm = (float) ($order->radius_km ?? 3);

        $shops = \App\Models\VendorShop::query()
            ->where('vendor_shops.is_active', true)
            ->whereNotNull('vendor_shops.latitude')
            ->whereNotNull('vendor_shops.longitude')
            ->join('vendors', 'vendors.id', '=', 'vendor_shops.vendor_id')
            ->select([
                'vendor_shops.id as shop_id',
                'vendor_shops.latitude',
                'vendor_shops.longitude',
                'vendor_shops.vendor_id',
                'vendors.subscription_tier',
            ])
            ->get();

        $ranked = collect();

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

            $ranked->push([
                'shop_id' => $shop->shop_id,
                'vendor_id' => $shop->vendor_id,
                'priority' => $this->subscriptionScore($shop->subscription_tier),
                'distance_km' => $distanceKm,
            ]);
        }

        if ($ranked->isEmpty()) return;

        // 🔥 Priority DESC, Distance ASC
        $ranked = $ranked
            ->sortBy([
                fn ($a) => -$a['priority'],
                fn ($a) => $a['distance_km'],
            ])
            ->values();

        foreach ($ranked as $entry) {
            \App\Models\OrderBroadcast::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'shop_id'  => $entry['shop_id'],
                ],
                [
                    'vendor_id' => $entry['vendor_id'],
                    'priority_score' => $entry['priority'],
                    'status' => 'pending',
                ]
            );
        }
    }

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

        return $earthKm * $c;
    }

    private function broadcastToNearbyShops(\App\Models\Order $order): void
    {
        $settings = app(\App\Services\AppSettings::class);

        $topN = (int) $settings->get('broadcast.top_n', 100);
        $ttlSeconds = (int) $settings->get('broadcast.ttl_seconds', 90);

        $ranked = app(\App\Services\ShopMatchingService::class)
            ->matchForOrderBroadcast($order, $topN);

                \Log::info('Broadcast: ranked result', [
            'order_id' => $order->id,
            'count' => is_array($ranked) ? count($ranked) : null,
            'sample' => is_array($ranked) ? array_slice($ranked, 0, 3) : null,
        ]);

        if (empty($ranked)) {
            \Log::info('Broadcast: no matched shops', ['order_id' => $order->id]);
            return;
        }

        $expiresAt = now()->addSeconds($ttlSeconds);

        foreach ($ranked as $entry) {
            \App\Models\OrderBroadcast::updateOrCreate(
                [
                    'order_id' => $order->id,
                    'shop_id'  => $entry['shop_id'],
                ],
                [
                    'vendor_id'       => $entry['vendor_id'],
                    'priority_score'  => $entry['priority_score'] ?? null,
                    'status'          => 'pending',
                    'expires_at'      => $expiresAt, // remove if you don't have this column
                ]
            );
        }
    }


}
