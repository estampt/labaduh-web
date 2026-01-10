<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorShop;
use App\Models\VendorService;
use App\Models\ServiceOption;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\DistanceCalculator;
use App\Services\Pricing\DeliveryFeeCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $r, PricingEngine $pricing, DistanceCalculator $distance, DeliveryFeeCalculator $deliveryFeeCalc)
    {
        $data = $r->validate([
            'vendor_id'=>['required','integer'],
            'shop_id'=>['required','integer'],
            'customer_id'=>['sometimes','integer'],
            'pickup_lat'=>['nullable','numeric'],'pickup_lng'=>['nullable','numeric'],
            'dropoff_lat'=>['nullable','numeric'],'dropoff_lng'=>['nullable','numeric'],
            'pickup_date'=>['nullable','date'],
            'pickup_time_start'=>['nullable','date_format:H:i'],
            'pickup_time_end'=>['nullable','date_format:H:i'],
            'notes'=>['nullable','string'],
            'items'=>['required','array','min:1'],
            'items.*.vendor_service_id'=>['required','integer'],
            'items.*.weight_kg'=>['nullable','numeric','min:0'],
            'items.*.items'=>['nullable','integer','min:0'],
            'items.*.option_ids'=>['sometimes','array'],
            'items.*.option_ids.*'=>['integer'],
        ]);

        $vendor = Vendor::findOrFail($data['vendor_id']);
        if(!$vendor->isApproved()) return response()->json(['message'=>'Vendor not available yet.'],422);

        $shop = VendorShop::where('vendor_id',$vendor->id)->findOrFail($data['shop_id']);
        if(!$shop->is_active) return response()->json(['message'=>'Shop is inactive.'],422);

        return DB::transaction(function () use ($data, $vendor, $shop, $pricing, $distance, $deliveryFeeCalc) {
            $distanceKm = 0.0;
            if(!empty($data['pickup_lat']) && !empty($data['pickup_lng']) && !empty($data['dropoff_lat']) && !empty($data['dropoff_lng'])){
                $distanceKm = $distance->haversineKm((float)$data['pickup_lat'],(float)$data['pickup_lng'],(float)$data['dropoff_lat'],(float)$data['dropoff_lng']);
            }
            $deliveryFee = $deliveryFeeCalc->calculate($vendor,$distanceKm);

            $order = Order::create([
                'vendor_id'=>$vendor->id,
                'shop_id'=>$shop->id,
                'customer_id'=>$data['customer_id'] ?? null,
                'pickup_lat'=>$data['pickup_lat'] ?? null, 'pickup_lng'=>$data['pickup_lng'] ?? null,
                'dropoff_lat'=>$data['dropoff_lat'] ?? null, 'dropoff_lng'=>$data['dropoff_lng'] ?? null,
                'pickup_date'=>$data['pickup_date'] ?? null,
                'pickup_time_start'=>$data['pickup_time_start'] ?? null,
                'pickup_time_end'=>$data['pickup_time_end'] ?? null,
                'status'=>'pending',
                'distance_km'=>round($distanceKm,2),
                'subtotal'=>0,'delivery_fee'=>round($deliveryFee,2),'total'=>0,
                'notes'=>$data['notes'] ?? null,
            ]);

            $subtotal = 0.0;

            foreach($data['items'] as $line){
                $vs = VendorService::where('vendor_id',$vendor->id)->findOrFail($line['vendor_service_id']);
                $weightKg=(float)($line['weight_kg'] ?? 0);
                $items=(int)($line['items'] ?? 0);

                $serviceCharge = $pricing->serviceCharge($vs,$weightKg);
                $optIds = $line['option_ids'] ?? [];
                $opts = (is_array($optIds) && count($optIds)>0) ? ServiceOption::whereIn('id',$optIds)->where('service_id',$vs->service_id)->get() : collect();

                $optionsTotal = $pricing->optionsTotal($opts,$weightKg,$items);
                $lineTotal = $serviceCharge + $optionsTotal;

                $itemRow = OrderItem::create([
                    'order_id'=>$order->id,
                    'vendor_service_id'=>$vs->id,
                    'weight_kg'=>$weightKg,
                    'items'=>$items,
                    'service_charge'=>round($serviceCharge,2),
                    'options_total'=>round($optionsTotal,2),
                    'line_total'=>round($lineTotal,2),
                ]);

                foreach($opts as $opt){
                    OrderItemOption::create([
                        'order_item_id'=>$itemRow->id,
                        'service_option_id'=>$opt->id,
                        'charge'=>round($pricing->optionCharge($opt,$weightKg,$items),2),
                    ]);
                }

                $subtotal += $lineTotal;
            }

            $order->update(['subtotal'=>round($subtotal,2), 'total'=>round($subtotal+$deliveryFee,2)]);
            return $order->load('items.options');
        });
    }

    public function show(Order $order) { return $order->load('items.options'); }

    public function updateStatus(Request $r, Order $order)
    {
        $data = $r->validate([
            'status' => ['required','in:pending,accepted,pickup_scheduled,picked_up,washing,ready_for_delivery,delivered,completed,cancelled'],
        ]);

        $user = $r->user();
        if ($user && $user->role === 'vendor' && (int)$user->vendor_id !== (int)$order->vendor_id) {
            return response()->json(['message' => 'Forbidden. Vendor ownership mismatch.'], 403);
        }

        $order->update(['status' => $data['status']]);

        // Apply vendor stats only once when order first becomes completed
        if ($data['status'] === 'completed' && is_null($order->stats_applied_at)) {
            DB::transaction(function () use ($order) {
                $order->refresh();

                if (!is_null($order->stats_applied_at)) return;

                $totalKg = (float) $order->items()->sum('weight_kg');

                $vendor = Vendor::find($order->vendor_id);
                if ($vendor) {
                    // Count per completed order (simple). If you want UNIQUE customers, we can enhance later.
                    $vendor->increment('customers_serviced_count', 1);
                    $vendor->increment('kilograms_processed_total', $totalKg);
                }

                $order->update(['stats_applied_at' => now()]);
            });
        }

        return $order->fresh();
    }
}
