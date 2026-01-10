<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorService;
use App\Models\VendorShop;
use App\Models\ServiceOption;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\DistanceCalculator;
use App\Services\Pricing\DeliveryFeeCalculator;
use Illuminate\Http\Request;
class VendorServiceController extends Controller
{
    public function list(Vendor $vendor){ if(!$vendor->isApproved()) return []; return VendorService::where('vendor_id',$vendor->id)->where('is_enabled',true)->get(); }
    public function upsert(Request $r, Vendor $vendor){ return VendorService::updateOrCreate(['vendor_id'=>$vendor->id,'service_id'=>$r->service_id], $r->all()); }
    public function toggle(Vendor $vendor, VendorService $vendorService){ $vendorService->update(['is_enabled'=>!$vendorService->is_enabled]); return $vendorService; }

    public function pricingPreview(Request $r, Vendor $vendor, PricingEngine $pricing, DistanceCalculator $distance, DeliveryFeeCalculator $deliveryFeeCalc)
    {
        $r->validate(['shop_id'=>['sometimes','integer'],'vendor_service_id'=>['required','integer'],'weight_kg'=>['required','numeric','min:0'],'items'=>['sometimes','integer','min:0'],'option_ids'=>['sometimes','array'],'option_ids.*'=>['integer'],'pickup_lat'=>['sometimes','numeric'],'pickup_lng'=>['sometimes','numeric'],'dropoff_lat'=>['sometimes','numeric'],'dropoff_lng'=>['sometimes','numeric']]);
        if(!$vendor->isApproved()) return response()->json(['message'=>'Vendor not available yet.'],422);

        if($r->filled('shop_id')){
            $shop = VendorShop::where('vendor_id',$vendor->id)->findOrFail((int)$r->shop_id);
            if(!$shop->is_active) return response()->json(['message'=>'Shop is inactive.'],422);
        }

        $vs = VendorService::where('vendor_id',$vendor->id)->findOrFail((int)$r->vendor_service_id);
        $weightKg=(float)$r->weight_kg; $items=(int)($r->items ?? 0);

        $serviceCharge = $pricing->serviceCharge($vs,$weightKg);
        $options = (is_array($r->option_ids) && count($r->option_ids)>0) ? ServiceOption::whereIn('id',$r->option_ids)->get() : collect();
        $optionsTotal = $pricing->optionsTotal($options,$weightKg,$items);

        $distanceKm=0.0; $deliveryFee=0.0;
        if($r->filled(['pickup_lat','pickup_lng','dropoff_lat','dropoff_lng'])){
            $distanceKm = $distance->haversineKm((float)$r->pickup_lat,(float)$r->pickup_lng,(float)$r->dropoff_lat,(float)$r->dropoff_lng);
            $deliveryFee = $deliveryFeeCalc->calculate($vendor,$distanceKm);
        }

        return ['vendor_id'=>$vendor->id,'shop_id'=>$r->shop_id ?? null,'vendor_service_id'=>$vs->id,'distance_km'=>round($distanceKm,2),'service_charge'=>round($serviceCharge,2),'options_total'=>round($optionsTotal,2),'delivery_fee'=>round($deliveryFee,2),'total'=>round($serviceCharge+$optionsTotal+$deliveryFee,2)];
    }
}
