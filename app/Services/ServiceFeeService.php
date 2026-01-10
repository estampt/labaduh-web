<?php

namespace App\Services;

use App\Models\Vendor;

class ServiceFeeService
{
    public function feePercentForVendor(Vendor $vendor): float
    {
        $tier = $vendor->subscription_tier ?? 'free';
        $cfg = config('monetization.subscription_tiers.' . $tier);
        return (float) ($cfg['service_fee_percent'] ?? 10);
    }

    public function computePlatformFee(float $subtotal, Vendor $vendor): float
    {
        $pct = $this->feePercentForVendor($vendor);
        return round($subtotal * ($pct / 100.0), 2);
    }
}
