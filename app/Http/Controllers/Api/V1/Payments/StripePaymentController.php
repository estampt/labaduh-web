<?php

namespace App\Http\Controllers\Api\V1\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Support\OrderTimelineKeys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class StripePaymentController extends Controller
{
    /**
     * Authorize (HOLD) using Stripe manual capture.
     * Call this AFTER order is WEIGHT_ACCEPTED.
     */
    public function authorizeCard(Request $request, Order $order)
    {
        $user = $request->user();

        // 🔐 ownership
        abort_unless((int) $order->customer_id === (int) $user->id, 403, 'Forbidden');

        // ✅ Only allow after weight accepted
        abort_unless(
            in_array($order->status, [
                OrderTimelineKeys::CREATED,
                OrderTimelineKeys::WEIGHT_ACCEPTED,
                OrderTimelineKeys::PUBLISHED,
                OrderTimelineKeys::AWAITING_PAYMENT
            ]),
            409,
            'Order not ready for payment.'
        );

        // ✅ Prevent duplicates
        abort_if(($order->payment_status ?? null) === 'paid', 409, 'Order already paid.');

        // ✅ DECIMAL pesos -> cents
        $finalTotalPesos = (float) $order->final_total;
        abort_if($finalTotalPesos <= 0, 422, 'Final total not set.');

        $finalCents = (int) round($finalTotalPesos * 100);

        // Optional buffer for authorization
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

            // ✅ Card-only → no redirect methods → no return_url needed
            $pi = $stripe->paymentIntents->create([
                'amount' => $bufferCents,
                'currency' => 'php',
                'capture_method' => 'manual',
                'payment_method_types' => ['card'],
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
                'metadata' => array_merge($payment->metadata ?? [], [
                    'pi_status' => $pi->status ?? null,
                ]),
            ]);

            // Keep order unpaid until webhook says otherwise
            $order->payment_status = $order->payment_status ?: 'unpaid';
            $order->save();

            return response()->json([
                'payment_id' => $payment->id,
                'payment_intent_id' => $pi->id,
                'client_secret' => $pi->client_secret,
                'authorized_amount_cents' => $bufferCents,
                'final_amount_cents' => $finalCents,
                'pi_status' => $pi->status,
            ]);
        });
    }

    /**
     * Capture the authorized funds for the final amount.
     * Webhook is source of truth for "paid".
     */
    public function capture(Request $request, Order $order)
    {
        $user = $request->user();

        // 🔐 ownership
        abort_unless((int) $order->customer_id === (int) $user->id, 403, 'Forbidden');

        // ✅ Must be ready
        abort_unless(
            $order->status === \App\Support\OrderTimelineKeys::WEIGHT_ACCEPTED,
            409,
            'Order not ready for capture.'
        );

        abort_if(($order->payment_status ?? null) === 'paid', 409, 'Order already paid.');

        $finalTotalPesos = (float) $order->final_total;
        abort_if($finalTotalPesos <= 0, 422, 'Final total not set.');

        $finalCents = (int) round($finalTotalPesos * 100);

        return DB::transaction(function () use ($order, $finalCents) {
            $payment = Payment::query()
                ->where('order_id', $order->id)
                ->where('provider', 'stripe')
                ->where('method', 'card')
                ->latest()
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(!$payment->provider_ref, 422, 'Missing Stripe PaymentIntent reference.');
            abort_if($payment->status === 'paid', 409, 'Payment already paid.');

            // Safety: final must not exceed authorized
            if (!empty($payment->amount_authorized) && $finalCents > (int) $payment->amount_authorized) {
                abort(422, 'Final amount exceeds authorized amount. Re-authorization required.');
            }

            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

            // ✅ Helpful pre-check: must be requires_capture
            $pi = $stripe->paymentIntents->retrieve($payment->provider_ref, []);
            if (($pi->status ?? null) !== 'requires_capture') {
                abort(422, 'PaymentIntent not ready to capture. Current status: '.($pi->status ?? 'unknown'));
            }

            $captured = $stripe->paymentIntents->capture(
                $payment->provider_ref,
                ['amount_to_capture' => $finalCents]
            );

            $payment->update([
                'amount_final' => $finalCents,
                'amount_captured' => $finalCents,
                'metadata' => array_merge($payment->metadata ?? [], [
                    'pi_status_after_capture' => $captured->status ?? null,
                ]),
            ]);

            return response()->json([
                'payment_id' => $payment->id,
                'payment_intent_id' => $payment->provider_ref,
                'stripe_status' => $captured->status ?? null,
            ]);
        });
    }

    /**
     * Direct card payment:
     * - no authorize step
     * - create PaymentIntent for immediate confirmation/capture
     * - frontend uses returned client_secret in PaymentSheet
     */
    public function payCardDirect(Request $request, Order $order)
    {
        $user = $request->user();

        abort_unless((int) $order->customer_id === (int) $user->id, 403, 'Forbidden');

        abort_if($order->payment_status === 'paid', 409, 'Order already paid.');

        abort_unless(
            in_array($order->status, [
                OrderTimelineKeys::WEIGHT_ACCEPTED,
                OrderTimelineKeys::AWAITING_PAYMENT,
                OrderTimelineKeys::PUBLISHED,
                OrderTimelineKeys::CREATED,
            ]),
            409,
            'Order not ready for payment.'
        );

        $amount = (int) round((float) ($order->final_total ?? $order->estimated_total ?? 0) * 100);

        abort_if($amount <= 0, 409, 'Invalid payment amount.');

        $existing = Payment::query()
            ->where('order_id', $order->id)
            ->where('provider', 'stripe')
            ->whereIn('status', [
                'requires_payment_method',
                'requires_confirmation',
                'requires_action',
                'processing',
                'succeeded',
            ])
            ->latest('id')
            ->first();

        if ($existing && $existing->status === 'succeeded') {
            abort(409, 'Order already paid.');
        }

        if ($existing && !empty($existing->provider_payment_intent_id) && !empty($existing->client_secret)) {
            return response()->json([
                'message' => 'Existing Stripe payment intent reused.',
                'payment_id' => $existing->id,
                'client_secret' => $existing->client_secret,
                'payment_intent_id' => $existing->provider_payment_intent_id,
                'amount' => $existing->amount,
                'currency' => strtoupper($existing->currency ?? 'SGD'),
            ]);
        }

        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        $currency = strtolower($order->currency ?: 'sgd');
        $idempotencyKey = 'ord_'.$order->id.'_stripe_direct_'.$user->id;

        DB::beginTransaction();

        try {
            $intent = $stripe->paymentIntents->create([
                'amount' => $amount,
                'currency' => $currency,
                'capture_method' => 'automatic',
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'customer_id' => (string) $user->id,
                    'payment_flow' => 'direct_capture',
                ],
                'description' => 'Labaduh Order #'.$order->id,
            ], [
                'idempotency_key' => $idempotencyKey,
            ]);

            $payment = Payment::create([
                'order_id' => $order->id,
                'provider' => 'stripe',
                'method' => 'card', // ✅ FIXED
                'currency' => strtoupper($currency),
                'amount' => $amount / 100,
                'status' => $intent->status,
                'provider_payment_intent_id' => $intent->id,
                'client_secret' => $intent->client_secret,
                'reference' => $intent->id,
                'idempotency_key' => $idempotencyKey,
                'meta' => [
                    'flow' => 'direct_capture',
                ],
            ]);

            Log::info('Stripe direct payment created', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'intent_id' => $intent->id,
                'status' => $intent->status,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Stripe payment intent created.',
                'payment_id' => $payment->id,
                'client_secret' => $intent->client_secret,
                'payment_intent_id' => $intent->id,
                'amount' => $amount / 100,
                'currency' => strtoupper($currency),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Stripe direct payment failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
