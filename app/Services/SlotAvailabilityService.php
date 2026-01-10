<?php

namespace App\Services;

use App\Models\ShopTimeSlot;

class SlotAvailabilityService
{
    public function isSlotAllowed(int $shopId, string $slotType, int $dayOfWeek, string $start, string $end): bool
    {
        return ShopTimeSlot::query()
            ->where('shop_id', $shopId)
            ->where('slot_type', $slotType)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->where('time_start', $start)
            ->where('time_end', $end)
            ->exists();
    }

    public function listSlots(int $shopId, string $slotType, int $dayOfWeek): array
    {
        return ShopTimeSlot::query()
            ->where('shop_id', $shopId)
            ->where('slot_type', $slotType)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->orderBy('time_start')
            ->get()
            ->toArray();
    }
}
