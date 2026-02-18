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


    /*
    |--------------------------------------------------------------------------
    | ORDERS BY SHOP — Active / Non-closed orders per vendor shop
    |--------------------------------------------------------------------------
    */
    public function getActiveOrderbyShop(Request $request, int $shopId)
    {

        $perPage = (int) ($request->get('per_page', 5));

        $orders = Order::query()
            ->select('orders.*')
            ->where('accepted_shop_id', $shopId)
            ->where('status', '!=', 'archived') // adjust if you have more closed statuses
            ->with([
                'customer:id,name,address_line1,address_line2,postal_code,latitude,longitude',
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
                'items.service:id,name,description',
                'items.options.serviceOption:id,name,description',
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

     /**
     * Transform an Order into the vendor-facing JSON shape.
     * Keep this inside the same controller (as you requested).
     */
    protected function transformOrderForVendor(Order $order, int $shopId): array
    {
        // Helpful computed summaries for UI
        $items = $order->items ?? collect();

        $itemsCount = $items->count();
        $servicesSummary = $items
            ->pluck('service.name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'id' => $order->id,
            'status' => $order->status,
            'created_at' => optional($order->created_at)?->toISOString(),
            'updated_at' => optional($order->updated_at)?->toISOString(),

            // Shop context
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

            // Customer
            'customer' => $order->customer ? [
                'id' => $order->customer->id,
                'name' => $order->customer->name,
                // 'profile_photo_url' => $order->customer->profile_photo_url, // enable after migration
            ] : null,

            // Quick UI helpers
            'items_count' => $itemsCount,
            'services' => $servicesSummary,

            // Full items (vendor needs to see services + options)
            'items' => $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'quantity' => $item->quantity ?? 1,

                    'service' => $item->service ? [
                        'id' => $item->service->id,
                        'name' => $item->service->name,
                        'description' => $item->service->description,
                    ] : null,

                    'options' => ($item->options ?? collect())->map(function ($opt) {
                        return [
                            'id' => $opt->id,
                            'qty' => $opt->qty ?? 1,
                            'service_option' => $opt->serviceOption ? [
                                'id' => $opt->serviceOption->id,
                                'name' => $opt->serviceOption->name,
                                'description' => $opt->serviceOption->description,
                            ] : null,
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),

            // If you have money fields, you can include them here safely:
            // 'subtotal' => $order->subtotal,
            // 'delivery_fee' => $order->delivery_fee,
            // 'total' => $order->total,
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
