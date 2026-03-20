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

        $paymentId = $obj->metadata->payment_id ?? null;

        Log::info('Stripe webhook parsed', [
            'event_id' => $event->id,
            'type' => $type,
            'payment_id' => $paymentId,
            'intent_id' => $obj->id ?? null,
            'intent_status' => $obj->status ?? null,
            'amount' => $obj->amount ?? null,
            'currency' => $obj->currency ?? null,
        ]);

        if (!$paymentId) {
            Log::warning('Stripe webhook missing payment_id metadata', [
                'event_id' => $event->id,
                'type' => $type,
                'intent_id' => $obj->id ?? null,
                'metadata' => isset($obj->metadata) ? (array) $obj->metadata : null,
            ]);

            return response('Missing payment_id metadata', 200);
        }

        $hash = hash('sha256', $payload);

        DB::transaction(function () use ($paymentId, $type, $obj, $sigVerified, $payload, $hash, $event) {
            $payment = Payment::query()->whereKey($paymentId)->lockForUpdate()->first();

            if (!$payment) {
                Log::warning('Stripe webhook payment not found', [
                    'payment_id' => $paymentId,
                    'event_id' => $event->id,
                    'type' => $type,
                ]);
                return;
            }

            Log::info('Stripe webhook payment found', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'status_before' => $payment->status,
                'provider_reference' => $payment->provider_reference ?? null,
            ]);

            $before = $payment->status;

            if (in_array($type, ['payment_intent.amount_capturable_updated', 'payment_intent.requires_capture'], true)) {
                if ($payment->status !== 'authorized') {
                    $payment->status = 'authorized';
                    $payment->authorized_at = $payment->authorized_at ?? now();
                    $payment->save();

                    Log::info('Stripe webhook payment marked authorized', [
                        'payment_id' => $payment->id,
                        'status_after' => $payment->status,
                    ]);
                }
            } elseif ($type === 'payment_intent.succeeded') {
                if ($payment->status !== 'paid') {
                    $payment->status = 'paid';
                    $payment->paid_at = $payment->paid_at ?? now();
                    $payment->save();

                    Log::info('Stripe webhook payment marked paid', [
                        'payment_id' => $payment->id,
                        'status_after' => $payment->status,
                        'paid_at' => $payment->paid_at,
                    ]);
                } else {
                    Log::info('Stripe webhook payment already paid', [
                        'payment_id' => $payment->id,
                    ]);
                }
            } elseif ($type === 'payment_intent.payment_failed') {
                if (!in_array($payment->status, ['failed', 'paid'], true)) {
                    $payment->status = 'failed';
                    $payment->failed_at = $payment->failed_at ?? now();
                    $payment->save();

                    Log::warning('Stripe webhook payment marked failed', [
                        'payment_id' => $payment->id,
                        'status_after' => $payment->status,
                        'failed_at' => $payment->failed_at,
                    ]);
                } else {
                    Log::info('Stripe webhook skipped failed update', [
                        'payment_id' => $payment->id,
                        'current_status' => $payment->status,
                    ]);
                }
            } else {
                Log::info('Stripe webhook event ignored for payment status mapping', [
                    'payment_id' => $payment->id,
                    'type' => $type,
                ]);
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

            Log::info('Stripe webhook event recorded', [
                'payment_id' => $payment->id,
                'event_id' => $event->id,
                'type' => $type,
                'status_before' => $before,
                'status_after' => $payment->status,
            ]);

            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->first();

            if (!$order) {
                Log::warning('Stripe webhook order not found', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                ]);
                return;
            }

            Log::info('Stripe webhook order found', [
                'order_id' => $order->id,
                'order_status_before' => $order->status,
                'payment_status_before' => $order->payment_status,
            ]);

            if ($payment->status === 'authorized') {
                if (($order->payment_status ?? null) !== 'authorized') {
                    $order->payment_status = 'authorized';
                    $order->save();

                    Log::info('Stripe webhook order payment_status updated to authorized', [
                        'order_id' => $order->id,
                    ]);

                    app(OrderTimelineRecorder::class)->record(
                        $order,
                        'payment_authorized',
                        'system',
                        null,
                        ['provider' => 'stripe', 'payment_id' => $payment->id]
                    );

                    Log::info('Stripe webhook timeline recorded: payment_authorized', [
                        'order_id' => $order->id,
                        'payment_id' => $payment->id,
                    ]);
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

                Log::info('Stripe webhook paid branch', [
                    'order_id' => $order->id,
                    'was_paid' => $wasPaid,
                    'should_advance' => $shouldAdvance,
                    'current_order_status' => $order->status,
                ]);

                if ($shouldAdvance) {
                    $order->status = OrderTimelineKeys::READY_FOR_WASHING;
                }

                $order->save();

                Log::info('Stripe webhook order saved after paid', [
                    'order_id' => $order->id,
                    'order_status_after' => $order->status,
                    'payment_status_after' => $order->payment_status,
                    'paid_at' => $order->paid_at ?? null,
                ]);

                if (!$wasPaid) {
                    app(OrderTimelineRecorder::class)->record(
                        $order,
                        'payment_paid',
                        'system',
                        null,
                        ['provider' => 'stripe', 'payment_id' => $payment->id]
                    );

                    Log::info('Stripe webhook timeline recorded: payment_paid', [
                        'order_id' => $order->id,
                        'payment_id' => $payment->id,
                    ]);
                }

                if ($shouldAdvance) {
                    app(OrderTimelineRecorder::class)->record(
                        $order,
                        OrderTimelineKeys::READY_FOR_WASHING,
                        'system',
                        null,
                        ['reason' => 'auto_after_payment']
                    );

                    Log::info('Stripe webhook timeline recorded: READY_FOR_WASHING', [
                        'order_id' => $order->id,
                        'payment_id' => $payment->id,
                    ]);
                }
            }

            Log::info('Stripe webhook transaction completed', [
                'event_id' => $event->id,
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
            ]);
        });

        Log::info('Stripe webhook finished successfully', [
            'event_id' => $event->id,
            'type' => $type,
        ]);

        return response('ok', 200);
    }
}
