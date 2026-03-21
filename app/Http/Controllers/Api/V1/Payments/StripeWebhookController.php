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
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(private PaymentEventRecorder $recorder) {}

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        Log::info('Stripe webhook hit', [
            'has_signature' => !empty($sigHeader),
            'payload_length' => strlen($payload),
        ]);

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('services.stripe.webhook_secret')
            );
            $sigVerified = 'yes';

            Log::info('Stripe webhook signature verified', [
                'event_id' => $event->id ?? null,
                'type' => $event->type ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response('Invalid signature', 400);
        }

        $type = $event->type;
        $obj = $event->data->object;

        $metadata = isset($obj->metadata) && method_exists($obj->metadata, 'toArray')
            ? $obj->metadata->toArray()
            : (array) ($obj->metadata ?? []);

        $paymentId = $metadata['payment_id'] ?? null;
        $orderId = $metadata['order_id'] ?? null;
        $intentId = $obj->id ?? null;

        Log::info('Stripe webhook parsed', [
            'event_id' => $event->id,
            'type' => $type,
            'payment_id' => $paymentId,
            'order_id' => $orderId,
            'intent_id' => $intentId,
            'intent_status' => $obj->status ?? null,
            'amount' => $obj->amount ?? null,
            'currency' => $obj->currency ?? null,
            'metadata' => $metadata,
        ]);

        $hash = hash('sha256', $payload);

        DB::transaction(function () use ($paymentId, $orderId, $intentId, $type, $obj, $sigVerified, $payload, $hash, $event) {
            $payment = null;

            if ($paymentId) {
                $payment = Payment::query()
                    ->whereKey($paymentId)
                    ->lockForUpdate()
                    ->first();
            }

            if (!$payment && $intentId) {
                $payment = Payment::query()
                    ->where('provider', 'stripe')
                    ->where('provider_payment_intent_id', $intentId)
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();
            }

            if (!$payment && $orderId) {
                $payment = Payment::query()
                    ->where('order_id', $orderId)
                    ->where('provider', 'stripe')
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();
            }

            if (!$payment) {
                Log::warning('Stripe webhook payment not found', [
                    'payment_id' => $paymentId,
                    'order_id' => $orderId,
                    'intent_id' => $intentId,
                    'event_id' => $event->id,
                    'type' => $type,
                ]);
                return;
            }

            Log::info('Stripe webhook payment found', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'status_before' => $payment->status,
                'provider_payment_intent_id' => $payment->provider_payment_intent_id ?? null,
            ]);

            $before = $payment->status;

            if (in_array($type, ['payment_intent.amount_capturable_updated', 'payment_intent.requires_capture'], true)) {
                if ($payment->status !== 'authorized') {
                    $payment->status = 'authorized';
                    $payment->authorized_at = $payment->authorized_at ?? now();
                    $payment->save();
                }
            } elseif ($type === 'payment_intent.succeeded') {
                if (!in_array($payment->status, ['paid', 'succeeded'], true)) {
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

            if (!$order) {
                Log::warning('Stripe webhook order not found', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                ]);
                return;
            }

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

                return;
            }

            if ($payment->status === 'paid') {
                $wasPaid = (($order->payment_status ?? null) === 'paid');

                $order->payment_status = 'paid';

                if (array_key_exists('paid_at', $order->getAttributes())) {
                    $order->paid_at = $order->paid_at ?? now();
                }

                $shouldAdvance = ($order->status === OrderTimelineKeys::AWAITING_PAYMENT);

                if ($shouldAdvance) {
                    $order->status = OrderTimelineKeys::READY_FOR_WASHING;
                }

                $order->save();

                if (!$wasPaid) {
                    app(OrderTimelineRecorder::class)->record(
                        $order,
                        'payment_paid',
                        'system',
                        null,
                        ['provider' => 'stripe', 'payment_id' => $payment->id]
                    );
                }

                if ($shouldAdvance) {
                    app(OrderTimelineRecorder::class)->record(
                        $order,
                        OrderTimelineKeys::READY_FOR_WASHING,
                        'system',
                        null,
                        ['reason' => 'auto_after_payment']
                    );
                }
            }
        });

        Log::info('Stripe webhook finished successfully', [
            'event_id' => $event->id,
            'type' => $type,
        ]);

        return response('ok', 200);
    }
}
