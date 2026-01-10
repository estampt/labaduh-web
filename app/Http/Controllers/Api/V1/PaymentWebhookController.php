<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use App\Models\PaymentIntent;
use App\Models\Payment;
use App\Models\Order;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    // NOTE: This is a scaffold. Add signature validation for production.
    public function paymongo(Request $r)
    {
        $payload = $r->all();
        $eventType = data_get($payload, 'data.attributes.type');
        $eventId = data_get($payload, 'data.id');

        WebhookLog::create([
            'provider' => 'paymongo',
            'event_type' => $eventType,
            'provider_event_id' => $eventId,
            'payload' => $payload,
        ]);

        // Minimal example: mark intent/order paid if event indicates success.
        // You must map PayMongo event types precisely based on their documentation.
        $providerIntentId = data_get($payload, 'data.attributes.data.id') ?? data_get($payload, 'data.attributes.resource.id');
        if ($providerIntentId) {
            $intent = PaymentIntent::where('provider_intent_id', $providerIntentId)->first();
            if ($intent && $intent->purpose === 'order' && $intent->order_id) {
                $intent->update(['status' => 'succeeded']);
                Payment::create([
                    'payment_intent_id' => $intent->id,
                    'amount' => $intent->amount,
                    'currency' => $intent->currency,
                    'method' => 'other',
                    'status' => 'paid',
                    'provider' => 'paymongo',
                    'provider_payment_id' => data_get($payload, 'data.id'),
                    'paid_at' => now(),
                ]);

                $order = Order::find($intent->order_id);
                if ($order) {
                    $order->update(['payment_status' => 'paid', 'paid_at' => now()]);
                }
            }
        }

        return response()->json(['ok' => true]);
    }
}
