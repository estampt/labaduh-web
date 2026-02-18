<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorShop;

use App\Services\OrderTimelineRecorder;
use App\Support\OrderTimelineKeys;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;



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

        $this->transition($order, OrderTimelineKeys::PICKED_UP, OrderTimelineKeys::WEIGHT_REVIEWED);

        app(OrderTimelineRecorder::class)->record(
            $order,
            OrderTimelineKeys::WEIGHT_REVIEWED,
            'vendor',
            $vendor->id,
            ['shop_id' => $shop->id]
        );

        return response()->json(['data' => $order->fresh()]);
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
