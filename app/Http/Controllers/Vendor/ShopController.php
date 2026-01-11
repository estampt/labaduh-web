<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\VendorShop;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $shops = class_exists(VendorShop::class)
            ? VendorShop::query()->where('vendor_id', $user->vendor_id)->latest()->get()->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name ?? ('Shop #' . $s->id),
                'lat' => $s->lat ?? $s->latitude ?? None,
                'lng' => $s->lng ?? $s->longitude ?? None,
            ])
            : [];

        return Inertia::render('Vendor/Shops', [
            'shops' => $shops,
        ]);
    }
}
