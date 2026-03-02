<?php

namespace App\Http\Controllers\Api\V1\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentEventRecorder;
use App\Support\OrderTimelineKeys;
use App\Services\OrderTimelineRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StripeWebhookController extends Controller
{
    public function __construct(private PaymentEventRecorder $recorder) {}

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('services.stripe.webhook_secret')
            );
            $sigVerified = 'yes';
        } catch (\Throwable $e) {
            return response('Invalid signature', 400);
        }

        $type = $event->type;
        $obj = $event->data->object;

        $paymentId = $obj->metadata->payment_id ?? null;
        if (!$paymentId) return response('Missing payment_id metadata', 200);

        $hash = hash('sha256', $payload);

        DB::transaction(function () use ($paymentId, $type, $obj, $sigVerified, $payload, $hash, $event) {
            $payment = Payment::query()->whereKey($paymentId)->lockForUpdate()->first();
            if (!$payment) return;

            $before = $payment->status;

            // ✅ Status mapping
            if (in_array($type, ['payment_intent.amount_capturable_updated', 'payment_intent.requires_capture'], true)) {
                if ($payment->status !== 'authorized') {
                    $payment->status = 'authorized';
                    $payment->authorized_at = $payment->authorized_at ?? now();
                    $payment->save();
                }
            } elseif ($type === 'payment_intent.succeeded') {
                if ($payment->status !== 'paid') {
                    $payment->status = 'paid';
                    $payment->paid_at = $payment->paid_at ?? now();
                    $payment->save();
                }
            } elseif ($type === 'payment_intent.payment_failed') {
                if (!in_array($payment->status, ['failed', 'paid'], true)) {
                    $payment->status = 'failed';
                    $payment->failed_at = $payment->failed_at ?? now();
                    $payment->save();
                }
            }

            $this->recorder->record(
                payment: $payment,
                provider: 'stripe',
                eventType: $type,
                providerEventId: $event->id,
                signatureVerified: $sigVerified,
                payload: json_decode($payload, true),
                statusBefore: $before,
                statusAfter: $payment->status,
                amount: isset($obj->amount) ? (int) $obj->amount : null,
                currency: isset($obj->currency) ? strtoupper((string) $obj->currency) : null,
                payloadHash: $hash
            );

            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->first();
            if (!$order) return;

            if (($order->payment_status ?? null) === 'paid') return;

            if ($payment->status === 'authorized') {
                if (($order->payment_status ?? null) !== 'authorized') {
                    $order->payment_status = 'authorized';
                    $order->save();

                    app(OrderTimelineRecorder::class)->record(
                        $order,
                        'payment_authorized',
                        'system',
                        null,
                        ['provider' => 'stripe', 'payment_id' => $payment->id]
                    );
                }
            }

            if ($payment->status === 'paid') {
                $order->payment_status = 'paid';

                if (array_key_exists('paid_at', $order->getAttributes())) {
                    $order->paid_at = $order->paid_at ?? now();
                }

                // ✅ advance order after paid
                if ($order->status === OrderTimelineKeys::WEIGHT_ACCEPTED) {
                    $order->status = OrderTimelineKeys::READY_FOR_WASHING;

                    app(OrderTimelineRecorder::class)->record(
                        $order,
                        OrderTimelineKeys::READY_FOR_WASHING,
                        'system',
                        null,
                        ['reason' => 'auto_after_payment']
                    );
                }

                $order->save();

                app(OrderTimelineRecorder::class)->record(
                    $order,
                    'payment_paid',
                    'system',
                    null,
                    ['provider' => 'stripe', 'payment_id' => $payment->id]
                );
            }
        });

        return response('ok', 200);
    }
}
