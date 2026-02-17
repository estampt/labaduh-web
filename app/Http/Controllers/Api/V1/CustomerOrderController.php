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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class CustomerOrderController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX — Order History
    | Supports: ?status=&cursor=&per_page=
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $user = $request->user();

        $perPage = (int) ($request->get('per_page', 10));

        $query = Order::query()
            ->where('customer_id', $user->id)
            ->orderByDesc('created_at');   // 👈 ensures date DESC

        /*
        |--------------------------------------------------------------------------
        | Status filter
        |--------------------------------------------------------------------------
        */
        $status = $request->get('status', 'active');

        if ($status === 'active') {
            $query->whereNotIn('status', $this->closedStatuses());
        } elseif ($status === 'closed') {
            $query->whereIn('status', $this->closedStatuses());
        } elseif ($status !== 'all') {
            // allow multiple: ?status=published,broadcasting
            $statuses = explode(',', $status);
            $query->whereIn('status', $statuses);
        }

        $orders = $query->cursorPaginate($perPage);

        /*
        |--------------------------------------------------------------------------
        | Reuse SHOW logic
        |--------------------------------------------------------------------------
        */
        $data = $orders->getCollection()
            ->map(fn ($order) => $this->transformFromShow($order));

        return response()->json([
            'data' => $data,
            'cursor' => $orders->nextCursor()?->encode(),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | LATEST — Non-closed orders shortcut
    |--------------------------------------------------------------------------
    */
    public function latest(Request $request)
    {
        $user = $request->user();
        $perPage = (int) ($request->get('per_page', 5));

        $orders = Order::query()
            ->select('orders.*') // ✅ safety: never drop order columns
            ->where('customer_id', $user->id)
            ->whereNotIn('status', $this->closedStatuses())
            ->with([
                'acceptedShop' => function ($q) {
                    $q->select([
                            'id',
                            'name',
                            'profile_photo_url',
                            'latitude',
                            'longitude',
                        ])
                        // ⭐ rating (from order_feedbacks)
                        ->addSelect([
                            'avg_rating' => DB::table('order_feedbacks')
                                ->selectRaw('AVG(rating)')
                                ->whereColumn('order_feedbacks.vendor_shop_id', 'vendor_shops.id'),
                            'ratings_count' => DB::table('order_feedbacks')
                                ->selectRaw('COUNT(*)')
                                ->whereColumn('order_feedbacks.vendor_shop_id', 'vendor_shops.id'),
                        ]);
                },

                // ✅ ADD THIS: service info per order item
                'items.service:id,name,description',
                // ✅ options + service_options info
                'items.options.serviceOption:id,name,description',
            ])
            ->orderByDesc('created_at')   // 👈 newest first
            ->cursorPaginate($perPage);

        $data = $orders->getCollection()
            ->map(fn ($order) => $this->transformFromShow($order));

        return response()->json([
            'data' => $data,
            'cursor' => $orders->nextCursor()?->encode(),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | SHOW — Single order (already exists)
    |--------------------------------------------------------------------------
    */
    public function show($id)
    {
        $order = Order::with([
            'items.options',
            'acceptedShop',
            'driver',
        ])->findOrFail($id);

        return response()->json([
            'data' => $this->transformFromShow($order)
        ]);
    }


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
                OrderTimelineKeys::CREATED,
                'customer',
                auth()->id()
            );
            return $order;
        });


        return response()->json([
            'data' => $order->load('items.options'),
        ]);
    }


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

    /*
    |--------------------------------------------------------------------------
    | Reusable transformer (shared by show/index/latest)
    |--------------------------------------------------------------------------
    */
    protected function transformFromShow(Order $order): array
    {
        // Ensure the same base payload your app expects (orders + relations)
        $order->loadMissing([
            'items.options.serviceOption:id,name,description', // ✅ ADD: option -> service_option
            'items.service:id,name,description',
            'acceptedShop',
            'driver',
        ]);

        $payload = $order->toArray();

        // ✅ Ensure items include service + option details consistently
        if ($order->relationLoaded('items')) {
            $payload['items'] = $order->items->map(function ($item) {

                $arr = $item->toArray();

                // ---- SERVICE ----
                $service = $item->service ?? null;
                $arr['service'] = $service ? [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                ] : null;

                // ---- OPTIONS (service_options.name/description) ----
                if ($item->relationLoaded('options')) {
                    $arr['options'] = $item->options->map(function ($opt) {
                        $optArr = $opt->toArray();

                        $serviceOption = $opt->serviceOption ?? null;
                        $optArr['service_option'] = $serviceOption ? [
                            'id' => $serviceOption->id,
                            'name' => $serviceOption->name,
                            'description' => $serviceOption->description,
                        ] : null;

                        return $optArr;
                    })->values()->toArray();
                }

                return $arr;
            })->values()->toArray();
        }

        $shop = $order->acceptedShop;

        $distanceKm = null;
        if (
            $shop &&
            is_numeric($order->search_lat) && is_numeric($order->search_lng) &&
            is_numeric($shop->latitude) && is_numeric($shop->longitude)
        ) {
            $distanceKm = $this->distanceKm(
                (float) $order->search_lat,
                (float) $order->search_lng,
                (float) $shop->latitude,
                (float) $shop->longitude
            );
        }

        // Add-only: shop summary for tracking UI
        $payload['vendor_shop'] = $shop ? [
            'id' => $shop->id,
            'name' => $shop->name,
            'profile_photo_url' => $shop->profile_photo_url,

            // rating fields are selected in latest() via subquery (avg_rating, ratings_count)
            'avg_rating' => isset($shop->avg_rating) && $shop->avg_rating !== null
                ? round((float) $shop->avg_rating, 1)
                : null,
            'ratings_count' => (int) ($shop->ratings_count ?? 0),

            'distance_km' => $distanceKm !== null ? round($distanceKm, 2) : null,
        ] : null;

        return $payload;
    }




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
            $broadcast = \App\Models\OrderBroadcast::updateOrCreate(
                [
                    'order_id' => $order->id,
                    'shop_id'  => $entry['shop_id'],
                ],
                [
                    'vendor_id'       => $entry['vendor_id'],
                    'priority_score'  => $entry['priority_score'] ?? null,
                    'status'          => 'pending',
                    //'expires_at'      => $expiresAt,
                ]
            );

            /*// ✅ Dispatch push only if not already sent/accepted/expired
            if (in_array($broadcast->status, ['pending'], true)) {
                // If you're inside a DB transaction, prefer afterCommit()
                \App\Jobs\SendOrderBroadcastPushJob::dispatch($broadcast->id);
            }*/

            // ✅ Only send push if this row was just created OR still pending
            if ($broadcast->wasRecentlyCreated || $broadcast->status === 'pending') {

                \App\Jobs\SendOrderBroadcastPushJob::dispatch($broadcast->id);

            }
        }
    }


    public function confirmDelivery(Request $request, Order $order)
    {
        $user = $request->user();

        // ✅ Security: only the owner customer can confirm
        if ((int) $order->customer_id !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // ✅ Idempotent: if already completed, just return
        if (($order->status ?? null) === 'completed') {
            return response()->json(['data' => $order->fresh()]);
        }

        // ✅ Only allow confirm when the order is actually delivered (or out_for_delivery if you prefer)
        $allowedStatuses = ['delivered']; // optionally: ['out_for_delivery','delivered']
        if (!in_array($order->status, $allowedStatuses, true)) {
            return response()->json([
                'message' => "Cannot confirm delivery unless status is: " . implode(', ', $allowedStatuses),
                'status' => $order->status,
            ], 422);
        }

        // ✅ Transition delivered -> completed
        $this->transition($order, OrderTimelineKeys::DELIVERED, OrderTimelineKeys::COMPLETED);

        // ✅ Record timeline as customer confirmation
        app(OrderTimelineRecorder::class)->record(
            $order,
            OrderTimelineKeys::COMPLETED,   // or a dedicated key like CUSTOMER_CONFIRMED if you have it
            'customer',
            $user->id,
            [
                'shop_id' => $order->shop_id ?? null,
                'order_id' => $order->id,
            ]
        );

        return response()->json(['data' => $order->fresh()]);
    }



    public function feedback(Request $request, Order $order)
    {
        $user = $request->user();

        // 🔐 Ensure ownership
        if ((int) $order->customer_id !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // ✅ Only completed orders can be reviewed
        if ($order->status !== 'completed') {
            return response()->json([
                'message' => 'Feedback allowed only for completed orders.',
                'status' => $order->status,
            ], 422);
        }

        // ❗ Ensure an accepted shop exists
        if (empty($order->accepted_shop_id)) {
            return response()->json([
                'message' => 'No accepted shop found for this order.'
            ], 422);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comments' => ['nullable', 'string', 'max:2000'],

            // ✅ NEW: real uploaded images (multipart)
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // 5MB each

            // (Optional) still allow urls if you want
            'image_urls' => ['nullable', 'array', 'max:10'],
            'image_urls.*' => ['string', 'max:2048'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Create / Update Feedback
        |--------------------------------------------------------------------------
        */
        $feedback = \App\Models\OrderFeedback::updateOrCreate(
            ['order_id' => $order->id],
            [
                'vendor_shop_id' => $order->accepted_shop_id,
                'customer_id' => $user->id,
                'rating' => (int) $data['rating'],
                'comments' => $data['comments'] ?? null,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Replace images (idempotent)
        | - If client sends images[] OR image_urls, we replace existing set
        | - For uploaded images, we also delete old stored files (best-effort)
        |--------------------------------------------------------------------------
        */
        $wantsReplaceImages = $request->hasFile('images') || array_key_exists('image_urls', $data);

        if ($wantsReplaceImages) {
            // delete old files (best-effort) if they were stored locally
            $old = $feedback->images()->get();
            foreach ($old as $img) {
                // if you stored paths like "storage/feedback/..." or "feedback/..."
                $path = $img->image_url ?? null;
                if ($path) {
                    // normalize "storage/xxx" -> "xxx" for public disk
                    $normalized = str_starts_with($path, 'storage/') ? substr($path, 8) : $path;

                    // only try deleting if it looks like our folder
                    if (str_starts_with($normalized, 'feedback/')) {
                        Storage::disk('public')->delete($normalized);
                    }
                }
            }

            // delete db rows
            $feedback->images()->delete();

            $rows = [];
            $sort = 0;

            // 1) save uploaded files
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    if (!$file || !$file->isValid()) continue;

                    $ext = $file->getClientOriginalExtension();
                    $filename = Str::uuid() . '.' . strtolower($ext);

                    // stored under: storage/app/public/feedback/{order_id}/...
                    $storedPath = $file->storeAs("feedback/{$order->id}", $filename, 'public');

                    // public URL: /storage/feedback/{order_id}/...
                    $publicUrl = Storage::disk('public')->url($storedPath);

                    $rows[] = [
                        'order_feedback_id' => $feedback->id,
                        'image_url' => $publicUrl,
                        'sort_order' => $sort++,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // 2) optionally also keep image_urls if provided
            if (array_key_exists('image_urls', $data)) {
                foreach (($data['image_urls'] ?? []) as $url) {
                    $rows[] = [
                        'order_feedback_id' => $feedback->id,
                        'image_url' => $url,
                        'sort_order' => $sort++,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (!empty($rows)) {
                \App\Models\OrderFeedbackImage::insert($rows);
            }
        }

        return response()->json([
            'data' => $feedback->load('images'),
        ], 201);
    }




    /*
    |--------------------------------------------------------------------------
    | Closed statuses helper
    |--------------------------------------------------------------------------
    */

    private function transition(Order $order, string $from, string $to): void
    {
        abort_unless($order->status === $from, 409, "Invalid status transition: {$order->status} -> {$to}");
        $order->update(['status' => $to]);
    }

    private function closedStatuses(): array
    {
        return [
            'completed',
            'cancelled',
            'closed',
        ];
    }
}
