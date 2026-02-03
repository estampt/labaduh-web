<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class CustomerOrderPricingController extends Controller
{
  public function approveFinal(Request $request, Order $order)
  {
    abort_unless((int)$order->customer_id === (int)auth()->id(), 403);
    abort_unless($order->pricing_status === 'final_proposed', 409);

    $order->pricing_status = 'approved';
    $order->approved_at = now();

    // set official totals to final
    $order->subtotal = $order->final_subtotal ?? $order->subtotal;
    $order->total = $order->final_total ?? $order->total;

    $order->save();

    return response()->json(['data' => $order->fresh()]);
  }

  public function rejectFinal(Request $request, Order $order)
  {
    abort_unless((int)$order->customer_id === (int)auth()->id(), 403);
    abort_unless($order->pricing_status === 'final_proposed', 409);

    $data = $request->validate([
      'reason' => ['nullable','string','max:500'],
    ]);

    $order->pricing_status = 'rejected';
    $order->pricing_notes = trim(($order->pricing_notes ?? '')."\nCustomer: ".($data['reason'] ?? 'Rejected'));
    $order->save();

    return response()->json(['data' => $order->fresh()]);
  }
}
