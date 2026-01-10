<?php
namespace App\Services\Pricing;
use App\Models\VendorService; use App\Models\ServiceOption; use Illuminate\Support\Collection;
class PricingEngine
{
    public function serviceCharge(VendorService $vs, float $weightKg): float { $min=(float)$vs->min_weight_kg; $base=(float)$vs->base_price; if($weightKg<=$min) return $base; return $base+(($weightKg-$min)*(float)$vs->price_per_extra_kg); }
    public function optionCharge(ServiceOption $opt, float $weightKg=0, int $items=0): float { $p=(float)$opt->price; return match($opt->price_type){ 'fixed'=>$p,'per_kg'=>$p*max(0,$weightKg),'per_item'=>$p*max(0,$items), default=>$p}; }
    public function optionsTotal(Collection $options, float $weightKg=0, int $items=0): float { $t=0.0; foreach($options as $o) $t+=$this->optionCharge($o,$weightKg,$items); return $t; }
}
