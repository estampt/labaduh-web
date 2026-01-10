<?php

namespace App\Services;

use App\Models\ShopDailyCapacity;
use App\Models\VendorShop;

class CapacityService
{
    public function getOrCreateDailyCapacity(VendorShop $shop, string $date): ShopDailyCapacity
    {
        return ShopDailyCapacity::firstOrCreate(
            ['shop_id' => $shop->id, 'date' => $date],
            ['max_orders' => null,'max_kg' => null,'orders_reserved' => 0,'kg_reserved' => 0]
        );
    }

    public function remainingForDate(VendorShop $shop, string $date): array
    {
        $cap = $this->getOrCreateDailyCapacity($shop, $date);
        $maxOrders = $cap->max_orders ?? $shop->default_max_orders_per_day;
        $maxKg = (float) ($cap->max_kg ?? $shop->default_max_kg_per_day);

        return [
            'max_orders' => (int)$maxOrders,
            'max_kg' => (float)$maxKg,
            'orders_reserved' => (int)$cap->orders_reserved,
            'kg_reserved' => (float)$cap->kg_reserved,
            'remaining_orders' => max(0, (int)$maxOrders - (int)$cap->orders_reserved),
            'remaining_kg' => max(0, $maxKg - (float)$cap->kg_reserved),
        ];
    }

    public function canReserve(VendorShop $shop, string $date, float $kgNeeded): bool
    {
        $r = $this->remainingForDate($shop, $date);
        return $r['remaining_orders'] >= 1 && $r['remaining_kg'] >= $kgNeeded;
    }

    public function reserve(VendorShop $shop, string $date, float $kgNeeded): void
    {
        $cap = $this->getOrCreateDailyCapacity($shop, $date);
        $cap->increment('orders_reserved', 1);
        $cap->increment('kg_reserved', $kgNeeded);
    }

    public function release(VendorShop $shop, string $date, float $kgNeeded): void
    {
        $cap = $this->getOrCreateDailyCapacity($shop, $date);
        $cap->orders_reserved = max(0, (int)$cap->orders_reserved - 1);
        $cap->kg_reserved = max(0, (float)$cap->kg_reserved - $kgNeeded);
        $cap->save();
    }
}
