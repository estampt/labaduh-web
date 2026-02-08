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

class VendorOrderStatusController extends Controller
{
    private function ensureOrderBelongsToShop(Order $order, VendorShop $shop): void
    {
        abort_unless((int)$order->accepted_shop_id === (int)$shop->id, 404);
        abort_unless($order->status !== 'cancelled', 409);
    }

    private function transition(Order $order, string $from, string $to): void
    {
        abort_unless($order->status === $from, 409, "Invalid status transition: {$order->status} -> {$to}");
        $order->update(['status' => $to]);
    }


    public function pickupScheduled(Request $request, Vendor $vendor, VendorShop $shop, Order $order)
    {
        $this->ensureOrderBelongsToShop($order, $shop);
        $this->canVendorMarkPickedUp($order);

        $this->transition($order, OrderTimelineKeys::ORDER_CREATED, OrderTimelineKeys::PICKUP_SCHEDULED);

        app(OrderTimelineRecorder::class)->record(
            $order,
            OrderTimelineKeys::PICKUP_SCHEDULED,
            'vendor',
            $vendor->id,
            [
                'shop_id' => $shop->id,
                'broadcast_id' => $broadcast->id,
            ]
        );
        return response()->json(['data' => $order->fresh()]);
    }


    public function markPickedUp(Request $request, Vendor $vendor, VendorShop $shop, Order $order)
    {
        // Ensure this order belongs to this vendor/shop
        abort_unless(($order->pickup_provider ?? 'vendor') === 'vendor', 409, 'pickup_provider is not vendor');

        abort_unless(in_array($order->status, [OrderTimelineKeys::ORDER_CREATED,OrderTimelineKeys::PUBLISHED,OrderTimelineKeys::PICKUP_SCHEDULED], true), 409, 'invalid status for mark-picked-up: '.$order->status);


        DB::transaction(function () use ($order, $vendor, $shop) {
            $order->update([
                'status' => 'picked_up',
            ]);

            // Record timeline event (if you already have recorder)
            app(\App\Services\OrderTimelineRecorder::class)->record(
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

    public function startWashing(Request $request, Vendor $vendor, VendorShop $shop, Order $order)
    {
      //$this->ensureOrderBelongsToShop($order, $shop);

      // ✅ auto-confirm if expired
      $this->autoApproveIfExpired($order);

       // Ensure this order belongs to this vendor/shop
        abort_unless(($order->pickup_provider ?? 'vendor') === 'vendor', 409, 'pickup_provider is not vendor');


      // ✅ must be approved (or auto-approved) before washing
      abort_unless(in_array($order->pricing_status, ['approved','auto_approved'], true), 409, 'Waiting for customer approval.');

      $this->transition($order, OrderTimelineKeys::PICKED_UP, OrderTimelineKeys::WASHING);

      app(OrderTimelineRecorder::class)->record(
            $order,
            OrderTimelineKeys::WASHING,
            'vendor',
            $vendor->id,
            [
                'shop_id' => $shop->id,
                'broadcast_id' => $broadcast->id,
            ]
        );
      return response()->json(['data' => $order->fresh()]);
    }




    public function markReady(Request $request, Vendor $vendor, VendorShop $shop, Order $order)
    {
        $this->ensureOrderBelongsToShop($order, $shop);
        $this->transition($order, OrderTimelineKeys::WASHING, OrderTimelineKeys::READY); // washing completed / pickup ready

        app(OrderTimelineRecorder::class)->record(
            $order,
            OrderTimelineKeys::READY,
            'vendor',
            $vendor->id,
            [
                'shop_id' => $shop->id,
                'broadcast_id' => $broadcast->id,
            ]
        );

        return response()->json(['data' => $order->fresh()]);
    }

    public function pickedUpFromShop(Request $request, Vendor $vendor, VendorShop $shop, Order $order)
    {
        $this->ensureOrderBelongsToShop($order, $shop);
        $this->transition($order, OrderTimelineKeys::READY, OrderTimelineKeys::OUT_FOR_DELIVERY);

        app(OrderTimelineRecorder::class)->record(
            $order,
            OrderTimelineKeys::OUT_FOR_DELIVERY,
            'vendor',
            $vendor->id,
            [
                'shop_id' => $shop->id,
                'broadcast_id' => $broadcast->id,
            ]
        );


        return response()->json(['data' => $order->fresh()]);
    }

    public function markDelivered(Request $request, Vendor $vendor, VendorShop $shop, Order $order)
    {
        $this->ensureOrderBelongsToShop($order, $shop);

        $this->transition($order, OrderTimelineKeys::OUT_FOR_DELIVERY, OrderTimelineKeys::DELIVERED);

        app(OrderTimelineRecorder::class)->record(
            $order,
            OrderTimelineKeys::DELIVERED,
            'vendor',
            $vendor->id,
            [
                'shop_id' => $shop->id,
                'broadcast_id' => $broadcast->id,
            ]
        );

        return response()->json(['data' => $order->fresh()]);
    }

    public function markCompleted(Request $request, Vendor $vendor, VendorShop $shop, Order $order)
    {
        $this->ensureOrderBelongsToShop($order, $shop);
        $this->transition($order, OrderTimelineKeys::DELIVERED, OrderTimelineKeys::COMPLETED);

        app(OrderTimelineRecorder::class)->record(
            $order,
            OrderTimelineKeys::COMPLETED,
            'vendor',
            $vendor->id,
            [
                'shop_id' => $shop->id,
                'broadcast_id' => $broadcast->id,
            ]
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
