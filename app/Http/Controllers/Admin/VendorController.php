<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\Vendor;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $vendors = class_exists(Vendor::class)
            ? Vendor::query()->latest()->paginate(20)->through(fn($v) => [
                'id' => $v->id,
                'name' => $v->name ?? ('Vendor #' . $v->id),
                'status' => $v->status ?? 'unknown',
                'rating' => $v->rating_avg ?? null,
                'created_at' => optional($v->created_at)->toDateTimeString(),
            ])
            : [];

        return Inertia::render('Admin/Vendors', [
            'vendors' => $vendors,
        ]);
    }

    public function show(Request $request, Vendor $vendor)
    {
        return Inertia::render('Admin/VendorDetail', [
            'vendor' => [
                'id' => $vendor->id,
                'name' => $vendor->name ?? ('Vendor #' . $vendor->id),
                'status' => $vendor->status ?? 'unknown',
                'rating' => $vendor->rating_avg ?? null,
                'metrics' => [
                    'orders_completed' => $vendor->orders_completed ?? 0,
                    'kg_processed' => $vendor->kg_processed ?? 0,
                    'unique_customers' => $vendor->unique_customers ?? 0,
                ],
            ],
        ]);
    }
}
