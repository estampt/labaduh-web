<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ShopService;
use App\Models\ShopServiceOption;
use App\Models\Vendor;
use App\Models\VendorShop;
use Illuminate\Http\Request;

class ShopServiceOptionController extends Controller
{
    public function index(Request $request, Vendor $vendor, VendorShop $shop, ShopService $shopService)
    {
        // Ensure shopService belongs to this shop
        abort_unless((int) $shopService->shop_id === (int) $shop->id, 404);

        $q = ShopServiceOption::query()
            ->where('shop_service_id', $shopService->id)
            ->with('serviceOption');

        if ($request->has('is_active')) {
            $q->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $q->orderBy('sort_order')->orderBy('id');

        return response()->json(['data' => $q->get()]);
    }

    public function store(Request $request, Vendor $vendor, VendorShop $shop, ShopService $shopService)
    {
        abort_unless((int) $shopService->shop_id === (int) $shop->id, 404);

        $data = $request->validate([
            'service_option_id' => ['required', 'integer', 'exists:service_options,id'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $data['price'] = $data['price'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? true;
        $data['sort_order'] = $data['sort_order'] ?? 0;

        // Friendly duplicate check: unique(shop_service_id, service_option_id)
        $exists = ShopServiceOption::where('shop_service_id', $shopService->id)
            ->where('service_option_id', (int) $data['service_option_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This add-on/option already exists for this service.',
                'errors' => [
                    'service_option_id' => ['Duplicate service_option_id for this shop service.'],
                ],
            ], 422);
        }

        $row = ShopServiceOption::create(array_merge($data, [
            'shop_service_id' => $shopService->id,
        ]))->load('serviceOption');

        return response()->json(['data' => $row], 201);
    }

    public function show(Request $request, Vendor $vendor, VendorShop $shop, ShopService $shopService, ShopServiceOption $shopServiceOption)
    {
        abort_unless((int) $shopService->shop_id === (int) $shop->id, 404);
        abort_unless((int) $shopServiceOption->shop_service_id === (int) $shopService->id, 404);

        return response()->json(['data' => $shopServiceOption->load('serviceOption')]);
    }

    public function update(Request $request, Vendor $vendor, VendorShop $shop, ShopService $shopService, ShopServiceOption $shopServiceOption)
    {
        abort_unless((int) $shopService->shop_id === (int) $shop->id, 404);
        abort_unless((int) $shopServiceOption->shop_service_id === (int) $shopService->id, 404);

        $data = $request->validate([
            // Usually DON'T allow changing service_option_id; keep it stable.
            // If you want to allow it later, tell me and I'll add uniqueness checks.
            'price' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $shopServiceOption->fill($data);
        $shopServiceOption->save();

        return response()->json(['data' => $shopServiceOption->load('serviceOption')]);
    }

    public function destroy(Request $request, Vendor $vendor, VendorShop $shop, ShopService $shopService, ShopServiceOption $shopServiceOption)
    {
        abort_unless((int) $shopService->shop_id === (int) $shop->id, 404);
        abort_unless((int) $shopServiceOption->shop_service_id === (int) $shopService->id, 404);

        $shopServiceOption->delete();

        return response()->json(['ok' => true]);
    }
}
