<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ShopService;
use App\Models\Vendor;
use App\Models\VendorShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorOrderPricingController extends Controller
{
  public function proposeFinal(Request $request, Vendor $vendor, VendorShop $shop, Order $order)
  {
    abort_unless((int)$order->accepted_shop_id === (int)$shop->id, 404);

    $data = $request->validate([
      'items' => ['required','array','min:1'],
      'items.*.order_item_id' => ['required','integer'],
      'items.*.actual_qty' => ['required','numeric','min:0.01'],
      'notes' => ['nullable','string'],
      'auto_confirm_minutes' => ['nullable','integer','min:5','max:1440'],
    ]);

    // Only allow propose when order is accepted/picked_up (before washing starts)
    abort_unless(in_array($order->status, ['accepted','picked_up'], true), 409);

    // Set estimate totals if not yet set
    if ($order->estimated_total <= 0) {
      $order->estimated_subtotal = $order->subtotal;
      $order->estimated_total = $order->total;
      $order->save();
    }

    $itemMap = collect($data['items'])->keyBy('order_item_id');

    DB::transaction(function () use ($order, $shop, $itemMap, $data) {

      $finalSubtotal = 0;

      foreach ($order->items as $it) {
        // Copy estimated if missing
        if (is_null($it->qty_estimated)) {
          $it->qty_estimated = $it->qty;
        }

        if ($itemMap->has($it->id)) {
          $actualQty = (float)$itemMap[$it->id]['actual_qty'];

          // pull shop pricing for this service
          $ss = ShopService::query()
            ->where('shop_id', $shop->id)
            ->where('service_id', $it->service_id)
            ->first();

          abort_unless($ss, 409, "Shop pricing missing for service_id {$it->service_id}");

          $servicePrice = $this->computeTieredMinPlus($ss, $actualQty);

          // options are flat — sum already saved in order_item_options
          $optionsTotal = (float)$it->options()->sum('computed_price');

          $finalLine = round($servicePrice + $optionsTotal, 2);

          $it->qty_actual = $actualQty;
          $it->final_price = $finalLine;

          // keep estimated snapshot
          $it->estimated_price = $it->estimated_price ?? $it->computed_price;
          $it->save();

          $finalSubtotal += $finalLine;
        } else {
          // if vendor didn’t provide actual, keep estimated as final (optional)
          $finalSubtotal += (float)($it->final_price ?? $it->computed_price);
        }
      }

      // reuse same fees for now (you can change later)
      $delivery = (float)$order->delivery_fee;
      $serviceFee = (float)$order->service_fee;
      $discount = (float)$order->discount;

      $finalTotal = round($finalSubtotal + $delivery + $serviceFee - $discount, 2);

      $order->pricing_status = 'final_proposed';
      $order->final_proposed_at = now();
      $order->auto_confirm_minutes = (int)($data['auto_confirm_minutes'] ?? $order->auto_confirm_minutes);
      $order->final_subtotal = round($finalSubtotal, 2);
      $order->final_total = $finalTotal;
      $order->pricing_notes = $data['notes'] ?? null;
      $order->save();
    });

    return response()->json(['data' => $order->fresh()->load('items.options')]);
  }

  private function computeTieredMinPlus(ShopService $ss, float $qty): float
  {
    $min = (float)($ss->minimum ?? 0);
    $minPrice = (float)($ss->min_price ?? 0);
    $ppu = (float)($ss->price_per_uom ?? 0);

    if ($min <= 0) return round($qty * $ppu, 2);
    if ($qty <= $min) return round($minPrice, 2);

    return round($minPrice + (($qty - $min) * $ppu), 2);
  }
}
