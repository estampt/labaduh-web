<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\VendorServicePrice;
use App\Models\VendorDeliveryPrice;

class PricingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $servicePrices = class_exists(VendorServicePrice::class)
            ? VendorServicePrice::query()->where('vendor_id', $user->vendor_id)->latest()->get()
            : [];

        $deliveryPrices = class_exists(VendorDeliveryPrice::class)
            ? VendorDeliveryPrice::query()->where('vendor_id', $user->vendor_id)->latest()->get()
            : [];

        return Inertia::render('Vendor/Pricing', [
            'servicePrices' => $servicePrices,
            'deliveryPrices' => $deliveryPrices,
        ]);
    }
}
