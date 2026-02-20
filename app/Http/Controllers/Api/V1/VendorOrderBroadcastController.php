<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderBroadcast;
use App\Models\Vendor;
use App\Models\VendorShop;

use App\Services\OrderTimelineRecorder;
use App\Support\OrderTimelineKeys;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorOrderBroadcastController extends Controller
{
    public function index(Request $request, Vendor $vendor, VendorShop $shop)
    {
        // vendor_owns_* middleware already guards vendor + shop

        $q = OrderBroadcast::query()
            ->where('shop_id', $shop->id)
            ->with(['order.items.options']) // adjust relations if needed
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        return response()->json(['data' => $q->get()]);
    }

    public function accept(Request $request, Vendor $vendor, VendorShop $shop, OrderBroadcast $broadcast)
    {
        abort_unless((int)$broadcast->shop_id === (int)$shop->id, 404);

        $order = Order::query()->where('id', $broadcast->order_id)->lockForUpdate()->firstOrFail();

        // Only accept if still publishable
        abort_unless($order->status === 'published', 409);

        DB::transaction(function () use ($order, $broadcast, $vendor, $shop) {
            // claim the order
            $order->update([
                'status' => OrderTimelineKeys::ACCEPTED,
                'accepted_vendor_id' => $vendor->id,
                'accepted_shop_id' => $shop->id,
            ]);

            // 🔹 STEP 6: record customer timeline event
            app(OrderTimelineRecorder::class)->record(
                $order,
                OrderTimelineKeys::ACCEPTED,
                'vendor',
                $vendor->id,
                [
                    'shop_id' => $shop->id,
                    'broadcast_id' => $broadcast->id,
                ]
            );

            // mark accepted broadcast row
            $broadcast->update(['status' => 'accepted']);

            // expire other broadcasts for the same order
            OrderBroadcast::query()
                ->where('order_id', $order->id)
                ->where('id', '!=', $broadcast->id)
                ->update(['status' => 'expired']);
        });



        return response()->json([
            'data' => $order->fresh()->load('items.options'),
        ]);
    }

    public function getBroadCastedOrderByOrderId(Request $request, int $shopId)
    {
        $perPage = (int) ($request->get('per_page', 50));
        $orderIdFilter = $request->get('order_id'); // ✅ NEW

        $query = DB::table('order_broadcasts as ob')
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
            ->join('users as u', 'u.id', '=', 'o.customer_id');

        // ✅ FILTER BY ORDER ID (optional)
        if (!empty($orderIdFilter)) {
            $query->where('ob.order_id', (int) $orderIdFilter);
        }

        $rows = $query
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
                'u.address_line1',
                'u.address_line2',
            ])
            ->orderByDesc('ob.sent_at')
            ->orderByDesc('ob.order_id')
            ->cursorPaginate($perPage);

        $itemsRows = collect($rows->items());
        $orderIds = $itemsRows->pluck('order_id')->filter()->unique()->values();

        // ==========================================================
        // FETCH ORDER ITEMS
        // ==========================================================
        $orderItems = $orderIds->isEmpty()
            ? collect()
            : DB::table('order_items')
                ->whereIn('order_id', $orderIds)
                ->select([
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
                ])
                ->orderBy('id')
                ->get();

        $orderItemIds = $orderItems->pluck('id')->values();

        // ==========================================================
        // FETCH ITEM OPTIONS
        // ==========================================================
        $orderItemOptions = $orderItemIds->isEmpty()
            ? collect()
            : DB::table('order_item_options')
                ->whereIn('order_item_id', $orderItemIds)
                ->select([
                    'id',
                    'order_item_id',
                    'service_option_id',
                    'service_option_name',
                    'price',
                    'is_required',
                    'computed_price',
                    'created_at',
                    'updated_at',
                ])
                ->orderBy('id')
                ->get();

        $optionsByItemId = $orderItemOptions->groupBy('order_item_id');

        $itemsWithOptions = $orderItems->map(function ($it) use ($optionsByItemId) {
            return [
                'id' => (int) $it->id,
                'order_id' => (int) $it->order_id,
                'service_id' => (int) $it->service_id,
                'service_name' => $it->service_name,

                'qty' => $it->qty,
                'qty_estimated' => $it->qty_estimated,
                'qty_actual' => $it->qty_actual,
                'uom' => $it->uom,

                'pricing_model' => $it->pricing_model,
                'minimum' => $it->minimum,
                'min_price' => $it->min_price,
                'price_per_uom' => $it->price_per_uom,
                'computed_price' => $it->computed_price,
                'estimated_price' => $it->estimated_price,
                'final_price' => $it->final_price,

                'created_at' => $it->created_at,
                'updated_at' => $it->updated_at,

                'options' => $optionsByItemId
                    ->get($it->id, collect())
                    ->map(function ($op) {
                        return [
                            'id' => (int) $op->id,
                            'order_item_id' => (int) $op->order_item_id,
                            'service_option_id' => (int) $op->service_option_id,
                            'service_option_name' => $op->service_option_name,
                            'price' => $op->price,
                            'is_required' => (bool) $op->is_required,
                            'computed_price' => $op->computed_price,
                            'created_at' => $op->created_at,
                            'updated_at' => $op->updated_at,
                        ];
                    })
                    ->values(),
            ];
        });

        $itemsByOrderId = $itemsWithOptions->groupBy('order_id');

        // ==========================================================
        // FINAL RESPONSE
        // ==========================================================
        $data = $itemsRows->map(function ($r) use ($itemsByOrderId) {
            $orderId = (int) $r->order_id;

            return [
                'order_id' => $orderId,

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
                    'address_line1' => $r->address_line1,
                    'address_line2' => $r->address_line2,
                ],

                // ✅ ITEMS INCLUDED
                'items' => $itemsByOrderId->get($orderId, collect())->values(),
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'cursor' => $rows->nextCursor()?->encode(),
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
                'ob.id as broadcast_id',
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
                        'broadcast_id' => $r->broadcast_id,
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

    public function getBroadcastById(Request $request, int $shopId)
    {
        $perPage = (int) ($request->get('per_page', 50));

        // ✅ NEW FILTER
        $broadcastIdFilter = $request->get('broadcast_id');

        $query = DB::table('order_broadcasts as ob')
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
            ->join('users as u', 'u.id', '=', 'o.customer_id');

        // ==========================================================
        // ✅ FILTER BY BROADCAST ID (optional)
        // ==========================================================
        if (!empty($broadcastIdFilter)) {
            $query->where('ob.id', (int) $broadcastIdFilter);
        }

        $rows = $query
            ->select([
                'ob.id as broadcast_id',
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
                'u.address_line1',
                'u.address_line2',
            ])
            ->orderByDesc('ob.sent_at')
            ->orderByDesc('ob.id')
            ->cursorPaginate($perPage);

        $itemsRows = collect($rows->items());
        $orderIds = $itemsRows->pluck('order_id')->filter()->unique()->values();

        // ==========================================================
        // FETCH ORDER ITEMS
        // ==========================================================
        $orderItems = $orderIds->isEmpty()
            ? collect()
            : DB::table('order_items')
                ->whereIn('order_id', $orderIds)
                ->select([
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
                ])
                ->orderBy('id')
                ->get();

        $orderItemIds = $orderItems->pluck('id')->values();

        // ==========================================================
        // FETCH ITEM OPTIONS
        // ==========================================================
        $orderItemOptions = $orderItemIds->isEmpty()
            ? collect()
            : DB::table('order_item_options')
                ->whereIn('order_item_id', $orderItemIds)
                ->select([
                    'id',
                    'order_item_id',
                    'service_option_id',
                    'service_option_name',
                    'price',
                    'is_required',
                    'computed_price',
                    'created_at',
                    'updated_at',
                ])
                ->orderBy('id')
                ->get();

        $optionsByItemId = $orderItemOptions->groupBy('order_item_id');

        $itemsWithOptions = $orderItems->map(function ($it) use ($optionsByItemId) {
            return [
                'id' => (int) $it->id,
                'order_id' => (int) $it->order_id,
                'service_id' => (int) $it->service_id,
                'service_name' => $it->service_name,

                'qty' => $it->qty,
                'qty_estimated' => $it->qty_estimated,
                'qty_actual' => $it->qty_actual,
                'uom' => $it->uom,

                'pricing_model' => $it->pricing_model,
                'minimum' => $it->minimum,
                'min_price' => $it->min_price,
                'price_per_uom' => $it->price_per_uom,
                'computed_price' => $it->computed_price,
                'estimated_price' => $it->estimated_price,
                'final_price' => $it->final_price,

                'created_at' => $it->created_at,
                'updated_at' => $it->updated_at,

                'options' => $optionsByItemId
                    ->get($it->id, collect())
                    ->map(function ($op) {
                        return [
                            'id' => (int) $op->id,
                            'order_item_id' => (int) $op->order_item_id,
                            'service_option_id' => (int) $op->service_option_id,
                            'service_option_name' => $op->service_option_name,
                            'price' => $op->price,
                            'is_required' => (bool) $op->is_required,
                            'computed_price' => $op->computed_price,
                            'created_at' => $op->created_at,
                            'updated_at' => $op->updated_at,
                        ];
                    })
                    ->values(),
            ];
        });

        $itemsByOrderId = $itemsWithOptions->groupBy('order_id');

        // ==========================================================
        // FINAL RESPONSE
        // ==========================================================
        $data = $itemsRows->map(function ($r) use ($itemsByOrderId) {
            $orderId = (int) $r->order_id;

            return [
                'broadcast_id' => (int) $r->broadcast_id,

                'broadcast' => [
                    'broadcast_id' => (int) $r->broadcast_id,
                    'status' => $r->broadcast_status,
                    'sent_at' => $r->sent_at,
                ],

                'order' => [
                    'order_id' => $orderId,
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
                    'address_line1' => $r->address_line1,
                    'address_line2' => $r->address_line2,
                ],

                // ✅ ITEMS
                'items' => $itemsByOrderId->get($orderId, collect())->values(),
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'cursor' => $rows->nextCursor()?->encode(),
        ]);
    }

}
