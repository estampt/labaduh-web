<?php

namespace App\Services;

use App\Models\VendorDeliveryPrice;
use App\Models\VendorServicePrice;

class PricingResolverService
{
    /**
     * Resolve service price rule for a vendor/shop/service/category.
     * Resolution order:
     * 1) shop-specific + category-specific
     * 2) shop-specific + category null
     * 3) vendor-wide + category-specific
     * 4) vendor-wide + category null
     * 5) system fallback config
     */
    public function resolveServiceRule(int $vendorId, ?int $shopId, int $serviceId, ?string $categoryCode): array
    {
        $enabled = (bool) config('pricing.vendor_override_enabled', true);
        if ($enabled) {
            $q = VendorServicePrice::query()
                ->where('vendor_id', $vendorId)
                ->where('is_active', true)
                ->where('service_id', $serviceId);

            $candidates = [];

            // 1-4
            $candidates[] = clone $q; $candidates[-1] = null; // placeholder to avoid php complexity in scaffold
        }

        // We'll implement in a straightforward way without clone trick:
        if ($enabled) {
            $rule = VendorServicePrice::query()
                ->where('vendor_id', $vendorId)->where('is_active', true)->where('service_id', $serviceId)
                ->where('shop_id', $shopId)->where('category_code', $categoryCode)->first();

            if (!$rule && $shopId) {
                $rule = VendorServicePrice::query()
                    ->where('vendor_id', $vendorId)->where('is_active', true)->where('service_id', $serviceId)
                    ->where('shop_id', $shopId)->whereNull('category_code')->first();
            }
            if (!$rule && $categoryCode) {
                $rule = VendorServicePrice::query()
                    ->where('vendor_id', $vendorId)->where('is_active', true)->where('service_id', $serviceId)
                    ->whereNull('shop_id')->where('category_code', $categoryCode)->first();
            }
            if (!$rule) {
                $rule = VendorServicePrice::query()
                    ->where('vendor_id', $vendorId)->where('is_active', true)->where('service_id', $serviceId)
                    ->whereNull('shop_id')->whereNull('category_code')->first();
            }

            if ($rule) {
                return [
                    'source' => 'vendor',
                    'pricing_model' => $rule->pricing_model,
                    'min_kg' => (float) $rule->min_kg,
                    'rate_per_kg' => (float) $rule->rate_per_kg,
                    'block_kg' => (float) $rule->block_kg,
                    'block_price' => (float) $rule->block_price,
                    'flat_price' => (float) $rule->flat_price,
                ];
            }
        }

        // System fallback
        return [
            'source' => 'system',
            'pricing_model' => 'per_kg_min',
            'min_kg' => (float) config('pricing.min_kg_per_line', 6.0),
            'rate_per_kg' => (float) config('pricing.rate_per_kg', 8.0),
            'block_kg' => null,
            'block_price' => null,
            'flat_price' => null,
        ];
    }

    public function resolveDeliveryRule(int $vendorId, ?int $shopId): array
    {
        $enabled = (bool) config('pricing.vendor_override_enabled', true);
        if ($enabled) {
            $rule = VendorDeliveryPrice::query()
                ->where('vendor_id', $vendorId)->where('is_active', true)
                ->where('shop_id', $shopId)->first();

            if (!$rule && $shopId) {
                $rule = VendorDeliveryPrice::query()
                    ->where('vendor_id', $vendorId)->where('is_active', true)
                    ->whereNull('shop_id')->first();
            }
            if ($rule) {
                return [
                    'source' => 'vendor',
                    'base_fee' => (float) $rule->base_fee,
                    'fee_per_km' => (float) $rule->fee_per_km,
                ];
            }
        }

        return [
            'source' => 'system',
            'base_fee' => (float) config('pricing.delivery_base_fee', 0.0),
            'fee_per_km' => (float) config('pricing.delivery_fee_per_km', 2.5),
        ];
    }
}
