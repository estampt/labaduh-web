<?php

namespace App\Http\Controllers\Api\V1\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            $order->status === \App\Support\OrderTimelineKeys::WEIGHT_ACCEPTED,
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
}
