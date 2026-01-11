<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

// Optional: use your models if present
use App\Models\Vendor;
use App\Models\Order;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $ordersToday = class_exists(Order::class) ? Order::query()->whereDate('created_at', now()->toDateString())->count() : 0;
        $activeVendors = class_exists(Vendor::class) ? Vendor::query()->where('status', 'approved')->count() : 0;

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'orders_today' => $ordersToday,
                'active_vendors' => $activeVendors,
                'revenue_today' => 0,
                'failed_matches' => 0,
            ],
        ]);
    }
}
