<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorShop;
use App\Models\MediaAttachment;

use App\Services\OrderTimelineRecorder;
use App\Support\OrderTimelineKeys;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;


use Illuminate\Support\Facades\Log;


class VendorOrderController extends Controller
{
    private function ensureOrderBelongsToShop(Order $order, VendorShop $shop): void
    {
        abort_unless((int)$order->accepted_shop_id === (int)$shop->id, 404);
        abort_unless($order->status !== 'cancelled', 409);
    }

    private function transition(Order $order, string $from, string $to): void
    {
        abort_unless(
            $order->status === $from,
            409,
            "Invalid status transition: {$order->status} -> {$to}"
        );

        // ✅ Set attribute directly
        $order->status = $to;

        // ✅ Save triggers model events + observers reliably
        $order->save();

        \Log::info('Order status transitioned', [
            'order_id' => $order->id,
            'from' => $from,
            'to' => $to,
        ]);
    }

    private function autoApproveIfExpired(Order $order): void
    {
        if ($order->pricing_status !== 'final_proposed') return;
        if (!$order->final_proposed_at) return;

        $mins = (int)($order->auto_confirm_minutes ?? 30);
        if (now()->diffInMinutes($order->final_proposed_at) < $mins) return;

        // auto-approve + lock totals
        $order->pricing_status = 'auto_approved';
        $order->approved_at = now();
        $order->subtotal = $order->final_subtotal ?? $order->subtotal;
        $order->total = $order->final_total ?? $order->total;
        $order->save();
    }


    public function getActiveOrderbyShop(Request $request, int $shopId)
    {
        $perPage = (int) ($request->get('per_page', 5));

        $orders = Order::query()
            ->select([
                'orders.*',
                'orders.subtotal',
                'orders.delivery_fee',
                'orders.service_fee',
                'orders.discount',
            ])
            ->where('accepted_shop_id', $shopId)
            ->where('status', '!=', 'archived')
            ->with([
                'customer:id,name,profile_photo_url,address_line1,address_line2,postal_code,latitude,longitude',
                'acceptedShop' => function ($q) {
                    $q->select([
                            'id',
                            'name',
                            'profile_photo_url',
                            'latitude',
                            'longitude',
                        ])
                        ->addSelect([
                            'avg_rating' => DB::table('order_feedbacks')
                                ->selectRaw('AVG(rating)')
                                ->whereColumn('order_feedbacks.vendor_shop_id', 'vendor_shops.id'),
                            'ratings_count' => DB::table('order_feedbacks')
                                ->selectRaw('COUNT(*)')
                                ->whereColumn('order_feedbacks.vendor_shop_id', 'vendor_shops.id'),
                        ]);
                },

                'items' => function ($q) {
                    $q->select([
                        'id',
                        'order_id',
                        'service_id',
                        'service_name',
                        'qty',
                        'qty_estimated',
                        'qty_actual',
                        'uom',
                        'pricing_model',
                        'minimum',
                        'min_price',
                        'price_per_uom',
                        'computed_price',
                        'estimated_price',
                        'final_price',
                        'created_at',
                        'updated_at',
                    ])->orderBy('id');
                },

                'items.options' => function ($q) {
                    $q->select([
                        'id',
                        'order_item_id',
                        'service_option_id',
                        'service_option_name',
                        'price',
                        'is_required',
                        'computed_price',
                        'created_at',
                        'updated_at',
                    ])->orderBy('id');
                },
            ])
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage);


        $data = collect($orders->items())
            ->map(fn (Order $order) => $this->transformOrderForVendor($order, $shopId))
            ->values();

        return response()->json([
            'data' => $data,
            'cursor' => $orders->nextCursor()?->encode(),
        ]);
    }

    protected function transformOrderForVendor(Order $order, int $shopId): array
    {
        $items = $order->items ?? collect();

        $itemsCount = $items->count();

        $servicesSummary = $items
            ->pluck('service_name')
            ->filter()
            ->map(fn ($s) => trim((string) $s))
            ->filter(fn ($s) => $s !== '')
            ->unique()
            ->values()
            ->all();

        return [
            'id' => $order->id,
            'status' => $order->status,
            'created_at' => optional($order->created_at)?->toISOString(),
            'updated_at' => optional($order->updated_at)?->toISOString(),
                // ✅ totals from orders table
            'subtotal' => $order->subtotal,
            'delivery_fee' => $order->delivery_fee,
            'service_fee' => $order->service_fee,
            'discount' => $order->discount,
            'shop_id' => $shopId,
            'accepted_shop' => $order->acceptedShop ? [
                'id' => $order->acceptedShop->id,
                'name' => $order->acceptedShop->name,
                'profile_photo_url' => $order->acceptedShop->profile_photo_url,
                'latitude' => $order->acceptedShop->latitude,
                'longitude' => $order->acceptedShop->longitude,
                'avg_rating' => $order->acceptedShop->avg_rating,
                'ratings_count' => $order->acceptedShop->ratings_count,
            ] : null,

            'customer' => $order->customer ? [
                'id' => $order->customer->id,
                'name' => $order->customer->name,
                'profile_photo_url' => $order->customer->profile_photo_url,
                'address_line1' => $order->customer->address_line1,
                'address_line2' => $order->customer->address_line2,
                'postal_code' => $order->customer->postal_code,
                'latitude' => $order->customer->latitude,
                'longitude' => $order->customer->longitude,
            ] : null,

            'items_count' => $itemsCount,
            'services' => $servicesSummary,

            'items' => $items->map(function ($item) {
                return [
                    'id' => $item->id,

                    // qty fields
                    'qty' => $item->qty,
                    'qty_estimated' => $item->qty_estimated,
                    'qty_actual' => $item->qty_actual,
                    'uom' => $item->uom,

                    // pricing fields
                    'pricing_model' => $item->pricing_model,
                    'minimum' => $item->minimum,
                    'min_price' => $item->min_price,
                    'price_per_uom' => $item->price_per_uom,

                    'computed_price' => $item->computed_price,
                    'estimated_price' => $item->estimated_price,
                    'final_price' => $item->final_price,

                    // service snapshot
                    'service' => [
                        'id' => $item->service_id,
                        'name' => $item->service_name,
//TODO To dispaly description or not
//                        'description' => $item->service_description,
                    ],

                    // options snapshot + pricing
                    'options' => ($item->options ?? collect())->map(function ($opt) {
                        return [
                            'id' => $opt->id,
                            'service_option_id' => $opt->service_option_id,

                            'qty' => $opt->qty,
                            'price' => $opt->price,
                            'is_required' => (bool) $opt->is_required,
                            'computed_price' => $opt->computed_price,

                            'service_option' => [
                                'id' => $opt->service_option_id,
                                'name' => $opt->service_option_name,
 //TODO To dispaly description or not
//                               'description' => $opt->service_option_description,
                            ],
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
        ];
    }


    // -----------------------
    // PICKUP FLOW
    // -----------------------

    public function pickupScheduled(Request $request, Vendor $vendor, VendorShop $shop, Order $order)
    {
        $this->ensureOrderBelongsToShop($order, $shop);

        // ✅ pickup-provider guard ONLY for pickup actions
        $this->canVendorMarkPickedUp($order);
        abort_unless(($order->pickup_provider ?? 'vendor') === 'vendor', 409, 'pickup_provider is not vendor');

        abort_unless(
            in_array($order->status, [OrderTimelineKeys::ACCEPTED], true),
            409,
            'invalid status for pick-up scheduled: '.$order->status
        );

        $this->transition($order, OrderTimelineKeys::ACCEPTED, OrderTimelineKeys::PICKUP_SCHEDULED);

        app(OrderTimelineRecorder::class)->record(
            $order,
            OrderTimelineKeys::PICKUP_SCHEDULED,
            'vendor',
            $vendor->id,
            ['shop_id' => $shop->id]
        );

        return response()->json(['data' => $order->fresh()]);
    }

    // renamed: markPickedUp -> pickedUp
    public function pickedUp(Request $request, Vendor $vendor, VendorShop $shop, Order $order)
    {
        $this->ensureOrderBelongsToShop($order, $shop);

        // ✅ pickup-provider guard ONLY for pickup actions
        $this->canVendorMarkPickedUp($order);
        abort_unless(($order->pickup_provider ?? 'vendor') === 'vendor', 409, 'pickup_provider is not vendor');

        // Keep your flexibility (allow pickup even if not scheduled in some flows)
        abort_unless(
            in_array($order->status, [
                OrderTimelineKeys::CREATED,
                OrderTimelineKeys::PUBLISHED,
                OrderTimelineKeys::ACCEPTED,
                OrderTimelineKeys::PICKUP_SCHEDULED
            ], true),
            409,
            'invalid status for picked-up: '.$order->status
        );

        DB::transaction(function () use ($order, $vendor, $shop) {
            $order->update(['status' => OrderTimelineKeys::PICKED_UP]);

            app(OrderTimelineRecorder::class)->record(
                $order,
                OrderTimelineKeys::PICKED_UP,
                'vendor',
                $vendor->id,
                ['shop_id' => $shop->id]
            );
        });

        return response()->json([
            'data' => $order->fresh()->load('items.options'),
        ]);
    }


    // -----------------------
    // WEIGHT FLOW (NO pickup-provider guards)
    // -----------------------

        // renamed: markWeightReviewed -> weightReviewed

    public function weightReviewed(Request $request, Vendor $vendor, VendorShop $shop, Order $order)
    {
        $this->ensureOrderBelongsToShop($order, $shop);
        $this->autoApproveIfExpired($order);

        $savedPaths = []; // track saved files for cleanup if transaction fails

        Log::info('📦 weightReviewed START', [
            'vendor_id' => $vendor->id,
            'shop_id'   => $shop->id,
            'order_id'  => $order->id,
            'has_image' => $request->hasFile('image'),
            'has_images'=> $request->hasFile('images'),
        ]);

        try {
            return DB::transaction(function () use (
                $request, $vendor, $shop, $order, &$savedPaths
            ) {

                /**
                 * =====================================================
                 * 1) Normalize multipart payload
                 * =====================================================
                 */
                $items = $request->input('items');

                if (is_string($items)) {
                    $decoded = json_decode($items, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $items = $decoded;
                    }
                }

                if (!is_array($items)) {
                    $items = [];
                }

                $request->merge(['items' => $items]);

                Log::info('📦 weightReviewed NORMALIZED', [
                    'vendor_id'    => $vendor->id,
                    'shop_id'      => $shop->id,
                    'order_id'     => $order->id,
                    'items_count'  => is_array($items) ? count($items) : 0,
                    'notes_exists' => $request->filled('notes'),
                ]);

                /**
                 * =====================================================
                 * 2) Validate
                 * =====================================================
                 */
                $validated = $request->validate([
                    'notes'     => ['nullable', 'string'],

                    'items'                  => ['nullable', 'array'],
                    'items.*.order_item_id'  => ['required_with:items', 'integer'],
                    'items.*.item_qty'       => ['required_with:items', 'numeric', 'min:0.01'],
                    'items.*.uploaded'       => ['nullable'],
                    'items.*.notes'          => ['nullable', 'string'],

                    'image'      => ['nullable', 'image', 'max:5120'],
                    'images'     => ['nullable', 'array', 'max:10'],
                    'images.*'   => ['image', 'max:5120'],
                ]);

                Log::info('📦 weightReviewed VALIDATED', [
                    'vendor_id'   => $vendor->id,
                    'shop_id'     => $shop->id,
                    'order_id'    => $order->id,
                    'items_count' => !empty($validated['items']) ? count($validated['items']) : 0,
                ]);

                /**
                 * =====================================================
                 * 4) Optional: save notes to orders table
                 * =====================================================
                 */
                if (!empty($validated['notes'])) {
                    $orderUpdates = [];

                    if (Schema::hasColumn('orders', 'weight_review_notes')) {
                        $orderUpdates['weight_review_notes'] = $validated['notes'];
                    } elseif (Schema::hasColumn('orders', 'pricing_notes')) {
                        $orderUpdates['pricing_notes'] = $validated['notes'];
                    }

                    if (!empty($orderUpdates)) {
                        $order->fill($orderUpdates)->save();

                        Log::info('📦 weightReviewed ORDER_NOTES_UPDATED', [
                            'order_id' => $order->id,
                            'columns'  => array_keys($orderUpdates),
                        ]);
                    }
                }

                /**
                 * =====================================================
                 * 5) Update qty_actual per order item (from items[])
                 * =====================================================
                 */
                $updatedItems = 0;

                if (!empty($validated['items']) && method_exists($order, 'items')) {
                    $normalizedItems = collect($validated['items'])->map(function ($row) {
                        return [
                            'order_item_id' => (int) ($row['order_item_id'] ?? 0),
                            'item_qty'      => (float) ($row['item_qty'] ?? 0),
                            'uploaded'      => filter_var($row['uploaded'] ?? false, FILTER_VALIDATE_BOOLEAN),
                            'notes'         => $row['notes'] ?? null,
                        ];
                    })->filter(fn ($r) => $r['order_item_id'] > 0 && $r['item_qty'] > 0);

                    $itemsById = $normalizedItems->keyBy('order_item_id');

                    $order->items()
                        ->whereIn('id', $itemsById->keys())
                        ->get()
                        ->each(function ($item) use ($itemsById, &$updatedItems) {
                            $row = $itemsById[$item->id];

                            if (Schema::hasColumn('order_items', 'qty_actual')) {
                                $item->qty_actual = $row['item_qty'];
                            } elseif (Schema::hasColumn('order_items', 'qty')) {
                                $item->qty = $row['item_qty'];
                            }

                            $item->save();
                            $updatedItems++;
                        });

                    Log::info('📦 weightReviewed ITEMS_UPDATED', [
                        'order_id'       => $order->id,
                        'updated_items'  => $updatedItems,
                        'requested_ids'  => $itemsById->keys()->values()->all(),
                    ]);
                }

                /**
                 * =====================================================
                 * 6) Upload photos (image + images[])
                 * =====================================================
                 */
                $savedAttachments = [];
                $files = [];

                if ($request->hasFile('image')) {
                    $files[] = $request->file('image');
                }

                if ($request->hasFile('images')) {
                    foreach ((array) $request->file('images') as $f) {
                        $files[] = $f;
                    }
                }

                if (!empty($files)) {
                    Log::info('📦 weightReviewed UPLOAD_START', [
                        'order_id'    => $order->id,
                        'files_count' => count($files),
                    ]);

                    foreach ($files as $file) {
                        $path = $file->store("orders/{$order->id}/weight-review", 'public');
                        $savedPaths[] = $path;

                        $savedAttachments[] = MediaAttachment::create([
                            'owner_type' => Order::class,
                            'owner_id'   => $order->id,
                            'disk'       => 'public',
                            'path'       => $path,
                            'mime'       => $file->getMimeType(),
                            'size_bytes' => $file->getSize(),
                            'category'   => MediaAttachment::CATEGORY_WEIGHT_REVIEW,
                        ]);
                    }

                    Log::info('📦 weightReviewed UPLOAD_DONE', [
                        'order_id'        => $order->id,
                        'saved_count'     => count($savedAttachments),
                        'saved_paths'     => $savedPaths,
                    ]);
                }

                /**
                 * =====================================================
                 * 3) Transition + timeline
                 * =====================================================
                 */
                $prevStatus = $order->status;

                $this->transition($order, OrderTimelineKeys::PICKED_UP, OrderTimelineKeys::WEIGHT_REVIEWED);

                app(OrderTimelineRecorder::class)->record(
                    $order,
                    OrderTimelineKeys::WEIGHT_REVIEWED,
                    'vendor',
                    $vendor->id,
                    ['shop_id' => $shop->id]
                );

                Log::info('📦 weightReviewed STATUS_MOVED', [
                    'order_id'    => $order->id,
                    'from_status' => $prevStatus,
                    'to_status'   => $order->fresh()->status,
                ]);

                /**
                 * =====================================================
                 * 7) Return refreshed order
                 * =====================================================
                 */
                $orderFresh = $order->fresh()->loadMissing('media');

                Log::info('✅ weightReviewed SUCCESS', [
                    'vendor_id'        => $vendor->id,
                    'shop_id'          => $shop->id,
                    'order_id'         => $order->id,
                    'updated_items'    => $updatedItems,
                    'uploaded_files'   => count($savedPaths),
                ]);

                return response()->json([
                    'data' => $orderFresh,
                ]);
            }, 3); // retries for deadlocks
        } catch (\Throwable $e) {
            // Best-effort cleanup for files saved before DB rollback
            if (!empty($savedPaths)) {
                foreach ($savedPaths as $p) {
                    try {
                        Storage::disk('public')->delete($p);
                    } catch (\Throwable $ignored) {
                        // ignore cleanup errors
                    }
                }
            }

            Log::error('❌ weightReviewed FAILED', [
                'vendor_id' => $vendor->id,
                'shop_id'   => $shop->id,
                'order_id'  => $order->id,
                'error'     => $e->getMessage(),
                'class'     => get_class($e),
            ]);

            throw $e; // let Laravel return proper error (422/500/etc)
        }
    }



    // renamed: markWeightAccepted -> weightAccepted
    public function weightAccepted(Request $request, Vendor $vendor, VendorShop $shop, Order $order)
    {
        $this->ensureOrderBelongsToShop($order, $shop);
        $this->autoApproveIfExpired($order);

        $this->transition($order, OrderTimelineKeys::WEIGHT_REVIEWED, OrderTimelineKeys::WEIGHT_ACCEPTED);

        app(OrderTimelineRecorder::class)->record(
            $order,
            OrderTimelineKeys::WEIGHT_ACCEPTED,
            'vendor',
            $vendor->id,
            ['shop_id' => $shop->id]
        );

        return response()->json(['data' => $order->fresh()]);
    }

    // -----------------------
    // WASH FLOW
    // -----------------------

    public function startWashing(Request $request, Vendor $vendor, VendorShop $shop, Order $order)
    {
        $this->ensureOrderBelongsToShop($order, $shop);

        // ✅ auto-confirm if expired
        $this->autoApproveIfExpired($order);

        // ✅ must be approved (or auto-approved) before washing
        abort_unless(
            in_array($order->pricing_status, ['approved', 'auto_approved'], true),
            409,
            'Waiting for customer approval.'
        );

        $this->transition($order, OrderTimelineKeys::WEIGHT_ACCEPTED, OrderTimelineKeys::WASHING);

        app(OrderTimelineRecorder::class)->record(
            $order,
            OrderTimelineKeys::WASHING,
            'vendor',
            $vendor->id,
            ['shop_id' => $shop->id]
        );

        return response()->json(['data' => $order->fresh()]);
    }

    // renamed: markReady -> ready
    public function ready(Request $request, Vendor $vendor, VendorShop $shop, Order $order)
    {
        $this->ensureOrderBelongsToShop($order, $shop);

        $this->transition($order, OrderTimelineKeys::WASHING, OrderTimelineKeys::READY);

        app(OrderTimelineRecorder::class)->record(
            $order,
            OrderTimelineKeys::READY,
            'vendor',
            $vendor->id,
            ['shop_id' => $shop->id]
        );

        return response()->json(['data' => $order->fresh()]);
    }

    // -----------------------
    // DELIVERY FLOW
    // -----------------------

    public function deliveryScheduled(Request $request, Vendor $vendor, VendorShop $shop, Order $order)
    {
        $this->ensureOrderBelongsToShop($order, $shop);
        $this->canVendorTouchDelivery($order);

        $this->autoApproveIfExpired($order);

        $this->transition($order, OrderTimelineKeys::READY, OrderTimelineKeys::DELIVERY_SCHEDULED);

        app(OrderTimelineRecorder::class)->record(
            $order,
            OrderTimelineKeys::DELIVERY_SCHEDULED,
            'vendor',
            $vendor->id,
            ['shop_id' => $shop->id]
        );

        return response()->json(['data' => $order->fresh()]);
    }

    // renamed: markOutForDelivery -> outForDelivery
    public function outForDelivery(Request $request, Vendor $vendor, VendorShop $shop, Order $order)
    {
        $this->ensureOrderBelongsToShop($order, $shop);
        $this->canVendorTouchDelivery($order);

        $this->autoApproveIfExpired($order);

        $this->transition($order, OrderTimelineKeys::DELIVERY_SCHEDULED, OrderTimelineKeys::OUT_FOR_DELIVERY);

        app(OrderTimelineRecorder::class)->record(
            $order,
            OrderTimelineKeys::OUT_FOR_DELIVERY,
            'vendor',
            $vendor->id,
            ['shop_id' => $shop->id]
        );

        return response()->json(['data' => $order->fresh()]);
    }

    // renamed: markDelivered -> delivered
    public function delivered(Request $request, Vendor $vendor, VendorShop $shop, Order $order)
    {
        $this->ensureOrderBelongsToShop($order, $shop);
        $this->canVendorTouchDelivery($order);

        $this->transition($order, OrderTimelineKeys::OUT_FOR_DELIVERY, OrderTimelineKeys::DELIVERED);

        app(OrderTimelineRecorder::class)->record(
            $order,
            OrderTimelineKeys::DELIVERED,
            'vendor',
            $vendor->id,
            ['shop_id' => $shop->id]
        );

        return response()->json(['data' => $order->fresh()]);
    }

    // renamed: markCompleted -> completed
    public function completed(Request $request, Vendor $vendor, VendorShop $shop, Order $order)
    {
        $this->ensureOrderBelongsToShop($order, $shop);

        $this->transition($order, OrderTimelineKeys::DELIVERED, OrderTimelineKeys::COMPLETED);

        app(OrderTimelineRecorder::class)->record(
            $order,
            OrderTimelineKeys::COMPLETED,
            'vendor',
            $vendor->id,
            ['shop_id' => $shop->id]
        );

        return response()->json(['data' => $order->fresh()]);
    }

    public function activeSummaryByShop(Request $request, int $vendor, int $shop)
    {
        // ✅ If you already enforce vendor/shop ownership elsewhere, keep that.
        // Otherwise, you can optionally validate that $shop belongs to $vendor.

        $base = Order::query()
            ->where('accepted_shop_id', $shop)
            ->whereNotIn('status', OrderTimelineKeys::closed());

        // Total active
        $totalActive = (clone $base)->count();

        // Group by status counts
        $byStatus = (clone $base)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => (string) $row->status,
                'count'  => (int) $row->count,
            ])
            ->values();

        return response()->json([
            'data' => [
                'vendor_id'     => $vendor,
                'shop_id'       => $shop,
                'total_active'  => $totalActive,
                'by_status'     => $byStatus,
                'closed_statuses' => OrderTimelineKeys::closed(),
            ],
        ]);
    }

    public function getBroadcastedOrderHeadersByShop(Request $request, int $shopId)
    {
        $perPage = (int) ($request->get('per_page', 10));

        $rows = DB::table('order_broadcasts as ob')
            ->where('ob.shop_id', $shopId)
            ->where('ob.status', 'sent')

            // Join orders
            ->join('orders as o', 'o.id', '=', 'ob.order_id')

            // Prevent showing orders already accepted by other shops
            ->where(function ($q) use ($shopId) {
                $q->whereNull('o.accepted_shop_id')
                  ->orWhere('o.accepted_shop_id', $shopId);
            })

            // Join customer
            ->join('users as u', 'u.id', '=', 'o.customer_id')

            ->select([
                'ob.order_id',
                'ob.status as broadcast_status',
                'ob.sent_at',

                'o.status as order_status',
                'o.pickup_mode',
                'o.delivery_mode',
                'o.currency',
                'o.total',
                'o.created_at',

                'u.id as customer_id',
                'u.name as customer_name',
                'u.profile_photo_url',

                // ✅ NEW — customer address
                'u.address_line1',
                'u.address_line2',
            ])

            ->orderByDesc('ob.sent_at')
            ->orderByDesc('ob.order_id')
            ->cursorPaginate($perPage);

        $data = collect($rows->items())
            ->map(function ($r) {
                return [
                    'order_id' => (int) $r->order_id,

                    'broadcast' => [
                        'status' => $r->broadcast_status,
                        'sent_at' => $r->sent_at,
                    ],

                    'order' => [
                        'status' => $r->order_status,
                        'pickup_mode' => $r->pickup_mode,
                        'delivery_mode' => $r->delivery_mode,
                        'currency' => $r->currency,
                        'total' => $r->total,
                        'created_at' => $r->created_at,
                    ],

                    'customer' => [
                        'id' => (int) $r->customer_id,
                        'name' => $r->customer_name,
                        'profile_photo_url' => $r->profile_photo_url,

                        // ✅ NEW
                        'address_line1' => $r->address_line1,
                        'address_line2' => $r->address_line2,
                    ],
                ];
            })
            ->values();

        return response()->json([
            'data' => $data,
            'cursor' => $rows->nextCursor()?->encode(),
        ]);
    }



    private function canVendorMarkPickedUp(Order $order): void
    {
        if ($order->pickup_provider === 'driver') {
            abort(409, 'Pickup is handled by driver. Vendor cannot mark picked up.');
        }
    }

    private function canVendorTouchDelivery(Order $order): void
    {
        if ($order->delivery_provider === 'driver') {
            abort(409, 'Delivery is handled by driver. Vendor cannot update delivery statuses.');
        }
    }
}
