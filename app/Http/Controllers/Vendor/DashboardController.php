<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Vendor/Dashboard', [
            'vendor' => [
                'vendor_id' => $user->vendor_id,
                'tier' => 'free',
            ],
            'stats' => [
                'orders_today' => 0,
                'kg_today' => 0,
                'capacity_kg' => 0,
                'capacity_orders' => 0,
                'earnings_today' => 0,
            ],
        ]);
    }
}
