<?php
namespace App\Services\Pricing;
use App\Models\DeliveryPricingRule; use App\Models\Vendor;
class DeliveryFeeCalculator
{
    public function calculate(Vendor $vendor, float $distanceKm): float
    {
        $rule = DeliveryPricingRule::query()->where('is_active', true)->where(function($q) use ($vendor){ $q->where('vendor_id',$vendor->id)->orWhereNull('vendor_id'); })->orderByRaw('vendor_id is null')->first();
        if(!$rule) return 0.0;
        $fee=(float)$rule->base_fee+($distanceKm*(float)$rule->per_km_rate);
        $fee=max($fee,(float)$rule->min_fee);
        if(!is_null($rule->max_fee)) $fee=min($fee,(float)$rule->max_fee);
        return $fee;
    }
}
