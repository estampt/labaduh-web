<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentIntentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function createOrderPaymentIntent(Order $order, Request $r, PaymentIntentService $svc)
    {
        $user = $r->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        if ($order->customer_id !== $user->id) return response()->json(['message' => 'Forbidden.'], 403);

        $data = $r->validate(['method' => ['nullable','in:gcash,card']]);
        $intent = $svc->createForOrder($order, $data['method'] ?? 'gcash');

        return response()->json([
            'payment_intent' => $intent,
            'checkout_url' => $intent->checkout_url,
        ], 201);
    }
}
