<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\Order;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $orders = class_exists(Order::class)
            ? Order::query()
                ->where('vendor_id', $user->vendor_id)
                ->latest()->paginate(20)->through(fn($o) => [
                    'id' => $o->id,
                    'status' => $o->status ?? 'unknown',
                    'fulfillment_mode' => $o->fulfillment_mode ?? 'third_party',
                    'total' => $o->total_amount ?? null,
                    'created_at' => optional($o->created_at)->toDateTimeString(),
                ])
            : [];

        return Inertia::render('Vendor/Orders', [
            'orders' => $orders,
        ]);
    }
}
