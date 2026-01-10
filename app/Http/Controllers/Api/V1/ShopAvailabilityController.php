<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\VendorShop;
use App\Services\CapacityService;
use App\Services\SlotAvailabilityService;
use Illuminate\Http\Request;

class ShopAvailabilityController extends Controller
{
    public function slots(VendorShop $shop, Request $r, SlotAvailabilityService $slots)
    {
        $data = $r->validate([
            'slot_type' => ['required','in:pickup,delivery'],
            'date' => ['required','date'],
        ]);

        $dow = (int) date('w', strtotime($data['date']));
        return $slots->listSlots($shop->id, $data['slot_type'], $dow);
    }

    public function capacity(VendorShop $shop, Request $r, CapacityService $capacity)
    {
        $data = $r->validate(['date' => ['required','date']]);
        return $capacity->remainingForDate($shop, $data['date']);
    }
}
