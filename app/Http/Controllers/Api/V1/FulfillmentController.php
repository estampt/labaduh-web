<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class FulfillmentController extends Controller
{
    public function set(Order $order, Request $r)
    {
        $user = $r->user();
        if (!$user || $order->customer_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $r->validate([
            'fulfillment_mode' => ['required','in:third_party,inhouse,walk_in'],
        ]);

        $order->update(['fulfillment_mode' => $data['fulfillment_mode']]);

        return response()->json($order);
    }
}
