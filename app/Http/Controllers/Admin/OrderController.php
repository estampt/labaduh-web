<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\Order;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = class_exists(Order::class)
            ? Order::query()->latest()->paginate(20)->through(fn($o) => [
                'id' => $o->id,
                'status' => $o->status ?? 'unknown',
                'fulfillment_mode' => $o->fulfillment_mode ?? 'third_party',
                'total' => $o->total_amount ?? null,
                'created_at' => optional($o->created_at)->toDateTimeString(),
            ])
            : [];

        return Inertia::render('Admin/Orders', [
            'orders' => $orders,
        ]);
    }
}
