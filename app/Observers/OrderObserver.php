<?php

namespace App\Observers;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use App\Services\PushNotificationService;
use App\Models\User;

class OrderObserver
{
    public function updated(Order $order): void
    {
        Log::info('✅ OrderObserver.updated fired', [
            'order_id' => $order->id,
            'old' => $order->getOriginal('status'),
            'new' => $order->status,
            'changed_status' => $order->wasChanged('status'),
        ]);

        if (!$order->wasChanged('status')) return;

        $status = (string) $order->status;

        // ✅ Notify Customer (your existing logic)
        app(PushNotificationService::class)->sendToUser(
            (int) $order->customer_id,
            'Order Update',
            "Your order is now {$status}.",
            [
                'type' => 'order_update',
                'route' => "/c/orders/{$order->id}",
                'order_id' => (int) $order->id,
                'status' => $status,
            ]
        );

        /*
        // ✅ NEW: Notify Vendor when customer marks delivered/completed
        if (in_array($status, ['delivered', 'completed'], true)) {

            // 1) Must have accepted shop
            $shopId = (int) ($order->accepted_shop_id ?? 0);
            if ($shopId <= 0) {
                Log::warning('Order delivered/completed but no accepted_shop_id', [
                    'order_id' => $order->id
                ]);
                return;
            }

            // 2) Find the vendor user using shop -> vendor -> user
            //    Adjust table/model names if yours differs
            $vendorId = \DB::table('vendor_shops')->where('id', $shopId)->value('vendor_id');

            if (!$vendorId) {
                Log::warning('No vendor_id found for shop', [
                    'order_id' => $order->id,
                    'shop_id' => $shopId,
                ]);
                return;
            }

            $vendorUserId = User::where('vendor_id', (int) $vendorId)->value('id');

            if (!$vendorUserId) {
                Log::warning('No user found for vendor_id', [
                    'order_id' => $order->id,
                    'vendor_id' => $vendorId,
                ]);
                return;
            }

            // 3) Send vendor push
            app(PushNotificationService::class)->sendToUser(
                (int) $vendorUserId,
                'Order Delivered',
                "Customer confirmed delivery for Order #{$order->id}.",
                [
                    'type' => 'order_delivered',
                    'route' => "/v/jobs/{$order->id}", // ✅ adjust if vendor job detail route differs
                    'order_id' => (int) $order->id,
                    'status' => $status,
                ]
            );
        } */
    }
}
