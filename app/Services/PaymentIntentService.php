<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentIntent;
use Illuminate\Support\Facades\DB;

class PaymentIntentService
{
    public function __construct(private PayMongoService $paymongo) {}

    /**
     * Create payment intent for an order. Returns PaymentIntent (with checkout_url if provider supports).
     */
    public function createForOrder(Order $order, string $method = 'gcash'): PaymentIntent
    {
        return DB::transaction(function () use ($order, $method) {
            $intent = PaymentIntent::create([
                'purpose' => 'order',
                'order_id' => $order->id,
                'vendor_id' => $order->vendor_id,
                'customer_id' => $order->customer_id,
                'amount' => (float) ($order->total_amount ?? 0),
                'currency' => config('payments.order_payment.currency', 'PHP'),
                'status' => 'pending',
                'provider' => 'paymongo',
            ]);

            // Build provider payload (scaffold)
            $payload = [
                'data' => [
                    'attributes' => [
                        'line_items' => [
                            [
                                'name' => 'Laundry Order #' . $order->id,
                                'amount' => (int) round(((float)($order->total_amount ?? 0)) * 100),
                                'currency' => 'PHP',
                                'quantity' => 1,
                            ]
                        ],
                        'payment_method_types' => $method === 'card'
                            ? ['card']
                            : ['gcash'],
                        'success_url' => config('app.url') . '/payment/success',
                        'cancel_url' => config('app.url') . '/payment/cancel',
                    ],
                ],
            ];

            $provider = $this->paymongo->createCheckout($payload);
            $intent->update([
                'provider_intent_id' => $provider['provider_intent_id'],
                'checkout_url' => $provider['checkout_url'],
                'provider_payload' => $provider['raw'],
                'status' => $provider['checkout_url'] ? 'requires_action' : 'pending',
            ]);

            // mark order payment pending
            $order->update(['payment_status' => 'pending']);

            return $intent->fresh();
        });
    }
}
