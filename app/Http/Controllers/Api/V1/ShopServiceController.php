<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ShopService;
use App\Models\Vendor;
use App\Models\VendorShop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShopServiceController extends Controller
{
    public function index(Request $request, Vendor $vendor, VendorShop $shop)
    {
        $q = ShopService::query()
            ->where('shop_id', $shop->id)
            ->with([
                'service',
                'options', // ✅ options already ARE ServiceOption models (with pivot)
            ]);

        if ($request->has('is_active')) {
            $q->where(
                'is_active',
                filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN)
            );
        }

        if ($request->filled('service_id')) {
            $q->where('service_id', (int) $request->query('service_id'));
        }

        $q->orderBy('sort_order')->orderBy('id');

        return response()->json(['data' => $q->get()]);
    }

    public function store(Request $request, Vendor $vendor, VendorShop $shop)
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],

            'pricing_model' => ['sometimes', 'string', 'max:32', Rule::in([
                'fixed', 'per_uom', 'tiered_min_plus', 'quote',
            ])],

            'uom' => ['required', 'string', 'max:16'],

            'minimum' => ['nullable', 'numeric', 'min:0'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'price_per_uom' => ['nullable', 'numeric', 'min:0'],

            'is_active' => ['sometimes', 'boolean'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        // Defaults
        $data['pricing_model'] = $data['pricing_model'] ?? 'tiered_min_plus';
        $data['currency'] = $data['currency'] ?? 'SGD';
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? true;

        // Pricing-model sanity rules
        $this->assertPricingRulesOrFail($data);

        // Friendly duplicate check for unique(shop_id, service_id)
        $exists = ShopService::where('shop_id', $shop->id)
            ->where('service_id', (int) $data['service_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This service already exists for this shop.',
                'errors' => ['service_id' => ['Duplicate service for this shop.']],
            ], 422);
        }

        $row = ShopService::create(array_merge($data, [
            'shop_id' => $shop->id,
        ]))->load('service');

        return response()->json(['data' => $row], 201);
    }

    public function show(Request $request, Vendor $vendor, VendorShop $shop, ShopService $shopService)
    {
        abort_unless((int) $shopService->shop_id === (int) $shop->id, 404);

        return response()->json(['data' => $shopService->load('service')]);
    }

    public function update(Request $request, Vendor $vendor, VendorShop $shop, ShopService $shopService)
    {
        abort_unless((int) $shopService->shop_id === (int) $shop->id, 404);

        $data = $request->validate([
            'service_id' => ['sometimes', 'integer', 'exists:services,id'],

            'pricing_model' => ['sometimes', 'string', 'max:32', Rule::in([
                'fixed', 'per_uom', 'tiered_min_plus', 'quote',
            ])],

            'uom' => ['sometimes', 'string', 'max:16'],

            'minimum' => ['nullable', 'numeric', 'min:0'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'price_per_uom' => ['nullable', 'numeric', 'min:0'],

            'is_active' => ['sometimes', 'boolean'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        // If changing service_id, ensure uniqueness
        if (array_key_exists('service_id', $data)) {
            $newServiceId = (int) $data['service_id'];

            $exists = ShopService::where('shop_id', $shop->id)
                ->where('service_id', $newServiceId)
                ->where('id', '!=', $shopService->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'This service already exists for this shop.',
                    'errors' => ['service_id' => ['Duplicate service for this shop.']],
                ], 422);
            }
        }

        // Validate pricing rules using merged state (existing + incoming)
        $merged = array_merge($shopService->toArray(), $data);
        $this->assertPricingRulesOrFail($merged);

        $shopService->fill($data);
        $shopService->save();

        return response()->json(['data' => $shopService->load('service')]);
    }

    public function destroy(Request $request, Vendor $vendor, VendorShop $shop, ShopService $shopService)
    {
        abort_unless((int) $shopService->shop_id === (int) $shop->id, 404);

        $shopService->delete();

        return response()->json(['ok' => true]);
    }

    private function assertPricingRulesOrFail(array $data): void
    {
        $pricingModel = strtolower((string) ($data['pricing_model'] ?? 'tiered_min_plus'));

        $minimum = $data['minimum'] ?? null;
        $minPrice = $data['min_price'] ?? null;
        $ppu = $data['price_per_uom'] ?? null;

        $errors = [];

        if ($pricingModel === 'fixed') {
            if ($minPrice === null) $errors['min_price'][] = 'min_price is required when pricing_model is fixed.';
        }

        if ($pricingModel === 'per_uom') {
            if ($ppu === null) $errors['price_per_uom'][] = 'price_per_uom is required when pricing_model is per_uom.';
        }

        if ($pricingModel === 'tiered_min_plus') {
            if ($minimum === null) $errors['minimum'][] = 'minimum is required when pricing_model is tiered_min_plus.';
            if ($minPrice === null) $errors['min_price'][] = 'min_price is required when pricing_model is tiered_min_plus.';
            if ($ppu === null) $errors['price_per_uom'][] = 'price_per_uom is required when pricing_model is tiered_min_plus.';
        }

        if (!empty($errors)) {
            abort(response()->json([
                'message' => 'Validation failed.',
                'errors' => $errors,
            ], 422));
        }
    }
}
