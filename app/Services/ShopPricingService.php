<?php

namespace App\Services;

use App\Models\ShopDeliveryPrice;
use App\Models\ShopServicePrice;
use App\Models\VendorShop;

class ShopPricingService
{
    /**
     * Calculate the line total for a single order line.
     * Expected $line: ['service_id'=>int, 'category_code'=>?string, 'kg'=>float]
     */
    public function priceLine(VendorShop $shop, array $line): array
    {
        $kg = (float) ($line['kg'] ?? 0);
        $serviceId = (int) ($line['service_id'] ?? 0);
        $category = $line['category_code'] ?? null;

        $price = ShopServicePrice::query()
            ->where('shop_id', $shop->id)
            ->where('service_id', $serviceId)
            ->where(function ($q) use ($category) {
                $q->whereNull('category_code');
                if ($category !== null) {
                    $q->orWhere('category_code', $category);
                }
            })
            ->where('is_active', true)
            ->orderByRaw('category_code is null') // prefer specific category if provided
            ->first();

        if (!$price) {
            return [
                'ok' => false,
                'reason' => 'NO_PRICE',
                'total' => 0,
                'detail' => null,
            ];
        }

        $model = $price->pricing_model;

        $lineTotal = 0.0;
        $chargedKg = $kg;

        if ($model === 'PER_KG') {
            $minKg = (float) ($price->min_kg ?? 0);
            if ($minKg > 0 && $kg < $minKg) {
                $chargedKg = $minKg;
            }
            $lineTotal = $chargedKg * (float) ($price->price_per_kg ?? 0);
        } elseif ($model === 'BLOCK_KG') {
            $blockKg = (float) ($price->block_kg ?? 0);
            $blockPrice = (float) ($price->block_price ?? 0);
            $blocks = $blockKg > 0 ? (int) ceil($kg / $blockKg) : 0;
            $lineTotal = $blocks * $blockPrice;
            $chargedKg = $blocks * $blockKg;
        } else { // FLAT
            $lineTotal = (float) ($price->flat_price ?? 0);
        }

        return [
            'ok' => true,
            'pricing_model' => $model,
            'kg_entered' => $kg,
            'kg_charged' => $chargedKg,
            'unit' => [
                'price_per_kg' => $price->price_per_kg,
                'min_kg' => $price->min_kg,
                'block_kg' => $price->block_kg,
                'block_price' => $price->block_price,
                'flat_price' => $price->flat_price,
            ],
            'total' => round($lineTotal, 2),
        ];
    }

    public function priceOrderLines(VendorShop $shop, array $lines): array
    {
        $items = [];
        $sum = 0.0;

        foreach ($lines as $idx => $line) {
            $calc = $this->priceLine($shop, $line);
            $items[] = array_merge(['index' => $idx], $calc);
            $sum += (float) ($calc['total'] ?? 0);
        }

        return [
            'items' => $items,
            'subtotal' => round($sum, 2),
        ];
    }

    public function computeInHouseDeliveryFee(VendorShop $shop, float $distanceKm): float
    {
        $delivery = ShopDeliveryPrice::query()->where('shop_id', $shop->id)->first();
        if (!$delivery) return 0.0;

        $fee = (float) $delivery->base_fee + ((float) $delivery->per_km_fee * max($distanceKm, 0));
        if ($delivery->min_fee !== null) $fee = max($fee, (float) $delivery->min_fee);
        if ($delivery->max_fee !== null) $fee = min($fee, (float) $delivery->max_fee);

        return round($fee, 2);
    }
}
