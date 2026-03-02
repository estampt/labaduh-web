<?php

namespace App\Http\Controllers\API\V1\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentEventRecorder;
use App\Support\OrderTimelineKeys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerPaymentController extends Controller
{
    public function __construct(private PaymentEventRecorder $recorder) {}

    // Stripe: authorize only (manual capture) — AFTER WEIGHT_ACCEPTED
    public function authorizeCard(Request $request, Order $order)
    {
        $user = $request->user();

        abort_unless((int) $order->customer_id === (int) $user->id, 403, 'Forbidden');
        abort_unless($order->status === OrderTimelineKeys::WEIGHT_ACCEPTED, 409, 'Order not ready for payment.');
        abort_if(($order->payment_status ?? null) === 'paid', 409, 'Order already paid.');

        $finalPesos = (float) $order->final_total;
        abort_if($finalPesos <= 0, 422, 'Final total not set.');

        $finalCents = (int) round($finalPesos * 100);
        $bufferCents = (int) round($finalCents * 1.20);
        $bufferCents = max($bufferCents, $finalCents);

        return DB::transaction(function () use ($order, $finalCents, $bufferCents) {
            $idempotencyKey = 'ord_'.$order->id.'_stripe_auth_'.Str::uuid();

            $payment = Payment::create([
                'order_id' => $order->id,
                'provider' => 'stripe',
                'method' => 'card',
                'status' => 'initiated',
                'currency' => 'PHP',
                'amount_final' => $finalCents,
                'amount_authorized' => $bufferCents,
                'idempotency_key' => $idempotencyKey,
                'dedupe_key' => 'stripe:order:'.$order->id.':auth:'.$idempotencyKey,
            ]);

            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

            $pi = $stripe->paymentIntents->create([
                'amount' => $bufferCents,
                'currency' => 'php',
                'capture_method' => 'manual',
                'payment_method_types' => ['card'], // ✅ no redirect methods
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'payment_id' => (string) $payment->id,
                ],
            ], [
                'idempotency_key' => $idempotencyKey,
            ]);

            $payment->update([
                'status' => $pi->status ?? 'requires_payment_method',
                'provider_ref' => $pi->id,
                'client_secret' => $pi->client_secret,
                'metadata' => array_merge($payment->metadata ?? [], ['pi_status' => $pi->status ?? null]),
            ]);

            $this->recorder->record(
                payment: $payment,
                provider: 'stripe',
                eventType: 'payment_intent.created',
                providerEventId: $pi->id,
                signatureVerified: 'unknown',
                payload: ['id' => $pi->id, 'status' => $pi->status ?? null],
                statusBefore: 'initiated',
                statusAfter: $payment->status,
                amount: $bufferCents,
                currency: 'PHP'
            );

            return response()->json([
                'payment_id' => $payment->id,
                'provider' => 'stripe',
                'payment_intent_id' => $pi->id,
                'client_secret' => $pi->client_secret,
            ]);
        });
    }

    // Stripe: capture after final_total confirmed (Webhook marks PAID)
    public function captureCard(Request $request, Order $order)
    {
        $user = $request->user();

        abort_unless((int) $order->customer_id === (int) $user->id, 403, 'Forbidden');
        abort_unless($order->status === OrderTimelineKeys::WEIGHT_ACCEPTED, 409, 'Order not ready for capture.');
        abort_if(($order->payment_status ?? null) === 'paid', 409, 'Order already paid.');

        $finalPesos = (float) $order->final_total;
        abort_if($finalPesos <= 0, 422, 'Final total not set.');
        $finalCents = (int) round($finalPesos * 100);

        return DB::transaction(function () use ($order, $finalCents) {
            $payment = Payment::query()
                ->where('order_id', $order->id)
                ->where('provider', 'stripe')
                ->where('method', 'card')
                ->latest()
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(!$payment->provider_ref, 422, 'Missing Stripe PaymentIntent reference.');

            if (!empty($payment->amount_authorized) && $finalCents > (int) $payment->amount_authorized) {
                abort(422, 'Final amount exceeds authorized amount. Re-authorization required.');
            }

            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

            $pi = $stripe->paymentIntents->retrieve($payment->provider_ref, []);
            if (($pi->status ?? null) !== 'requires_capture') {
                abort(422, 'PaymentIntent not ready to capture. Current status: '.($pi->status ?? 'unknown'));
            }

            $captured = $stripe->paymentIntents->capture(
                $payment->provider_ref,
                ['amount_to_capture' => $finalCents]
            );

            $payment->update([
                'amount_captured' => $finalCents,
                'metadata' => array_merge($payment->metadata ?? [], [
                    'pi_status_after_capture' => $captured->status ?? null,
                ]),
            ]);

            $this->recorder->record(
                payment: $payment,
                provider: 'stripe',
                eventType: 'payment_intent.capture_called',
                providerEventId: $captured->id ?? null,
                signatureVerified: 'unknown',
                payload: ['id' => $captured->id ?? null, 'status' => $captured->status ?? null],
                statusBefore: $payment->getOriginal('status'),
                statusAfter: $payment->status,
                amount: $finalCents,
                currency: 'PHP'
            );

            return response()->json([
                'payment_id' => $payment->id,
                'provider' => 'stripe',
                'payment_intent_id' => $payment->provider_ref,
                'stripe_status' => $captured->status ?? null,
            ]);
        });
    }
}
