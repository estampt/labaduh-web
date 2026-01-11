<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PricingController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Pricing', [
            'systemPricing' => [
                'min_kg_per_line' => config('pricing.min_kg_per_line', 6),
                'rate_per_kg' => config('pricing.rate_per_kg', 0),
                'delivery_base_fee' => config('pricing.delivery_base_fee', 0),
                'delivery_fee_per_km' => config('pricing.delivery_fee_per_km', 0),
            ],
            'courier' => [
                'default_provider' => config('fulfillment.third_party.default_provider', 'lalamove'),
                'markup_percent' => config('fulfillment.third_party.markup_percent', 0),
            ],
        ]);
    }
}
