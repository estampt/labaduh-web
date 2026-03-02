<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Support\OrderTimelineKeys;
use App\Services\OrderTimelineRecorder;
use Illuminate\Support\Facades\DB;

class OrderPaymentSync
{
    public function syncFromPayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            /** @var Order $order */
            $order = Order::query()
                ->whereKey($payment->order_id)
                ->lockForUpdate()
                ->first();

            if (!$order) return;

            // ✅ Idempotent: if already paid, don't do anything
            if (($order->payment_status ?? null) === 'paid') {
                return;
            }

            // -------------------------------
            // Stripe AUTHORIZED
            // -------------------------------
            if ($payment->status === 'authorized') {
                // only set authorized if not already paid
                if (($order->payment_status ?? null) !== 'authorized') {
                    $order->payment_status = 'authorized';
                    $order->save();

                    // Optional timeline record
                    app(OrderTimelineRecorder::class)->record(
                        $order,
                        'payment_authorized', // add a constant if you want, or keep as string
                        'system',
                        null,
                        ['payment_id' => $payment->id, 'provider' => $payment->provider]
                    );
                }

                return;
            }

            // -------------------------------
            // PAID (Stripe or Xendit)
            // -------------------------------
            if ($payment->status === 'paid') {
                $order->payment_status = 'paid';
                $order->paid_at = $order->paid_at ?? now(); // if you have paid_at column
                $order->save();

                app(OrderTimelineRecorder::class)->record(
                    $order,
                    'payment_paid', // add a constant if you want
                    'system',
                    null,
                    ['payment_id' => $payment->id, 'provider' => $payment->provider]
                );

                // ✅ OPTIONAL: move order forward ONLY when appropriate
                // Your flow: customer confirms weight via weightAccepted()
                // So only auto-progress if they've already accepted the weight.
                if ($order->status === OrderTimelineKeys::WEIGHT_ACCEPTED) {
                    // If you have a WASHING constant, use it.
                    // Otherwise, choose the correct "next" status in your system.
                    if (defined(OrderTimelineKeys::class.'::WASHING')) {
                        $order->status = OrderTimelineKeys::WASHING;
                    } else {
                        // fallback: keep status unchanged to avoid breaking transitions
                        // $order->status = 'washing';
                    }
                    $order->save();

                    app(OrderTimelineRecorder::class)->record(
                        $order,
                        $order->status, // or OrderTimelineKeys::WASHING
                        'system',
                        null,
                        ['reason' => 'auto_after_payment']
                    );
                }

                return;
            }

            // If failed/expired: you can optionally set order.payment_status = 'unpaid' or leave as-is.
        });
    }
}
