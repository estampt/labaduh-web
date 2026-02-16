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
                'status' => OrderTimelineKeys::ORDER_ACCEPTED,
                'accepted_vendor_id' => $vendor->id,
                'accepted_shop_id' => $shop->id,
            ]);

            // 🔹 STEP 6: record customer timeline event
            app(OrderTimelineRecorder::class)->record(
                $order,
                OrderTimelineKeys::ORDER_ACCEPTED,
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
}
