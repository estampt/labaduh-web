<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorDeliveryPrice;
use App\Models\VendorServicePrice;
use Illuminate\Http\Request;

class VendorPricingController extends Controller
{
    public function index(Request $r)
    {
        $user = $r->user();
        if (!$user || $user->role !== 'vendor') return response()->json(['message' => 'Forbidden.'], 403);

        $vendorId = $user->vendor_id;
        return response()->json([
            'service_prices' => VendorServicePrice::where('vendor_id', $vendorId)->orderBy('id')->get(),
            'delivery_prices' => VendorDeliveryPrice::where('vendor_id', $vendorId)->orderBy('id')->get(),
        ]);
    }

    public function upsertServicePrices(Request $r)
    {
        $user = $r->user();
        if (!$user || $user->role !== 'vendor') return response()->json(['message' => 'Forbidden.'], 403);
        $vendorId = $user->vendor_id;

        $data = $r->validate([
            'prices' => ['required','array','min:1'],
            'prices.*.id' => ['nullable','integer'],
            'prices.*.shop_id' => ['nullable','integer'],
            'prices.*.service_id' => ['required','integer'],
            'prices.*.category_code' => ['nullable','string','max:50'],
            'prices.*.pricing_model' => ['required','in:per_kg_min,per_block,flat'],
            'prices.*.min_kg' => ['nullable','numeric','min:0'],
            'prices.*.rate_per_kg' => ['nullable','numeric','min:0'],
            'prices.*.block_kg' => ['nullable','numeric','min:0'],
            'prices.*.block_price' => ['nullable','numeric','min:0'],
            'prices.*.flat_price' => ['nullable','numeric','min:0'],
            'prices.*.is_active' => ['nullable','boolean'],
        ]);

        $saved = [];
        foreach ($data['prices'] as $p) {
            $saved[] = VendorServicePrice::updateOrCreate(
                ['id' => $p['id'] ?? null, 'vendor_id' => $vendorId],
                [
                    'vendor_id' => $vendorId,
                    'shop_id' => $p['shop_id'] ?? null,
                    'service_id' => $p['service_id'],
                    'category_code' => $p['category_code'] ?? null,
                    'pricing_model' => $p['pricing_model'],
                    'min_kg' => $p['min_kg'] ?? null,
                    'rate_per_kg' => $p['rate_per_kg'] ?? null,
                    'block_kg' => $p['block_kg'] ?? null,
                    'block_price' => $p['block_price'] ?? null,
                    'flat_price' => $p['flat_price'] ?? null,
                    'is_active' => $p['is_active'] ?? true,
                ]
            );
        }

        return response()->json(['saved' => $saved]);
    }

    public function upsertDeliveryPrice(Request $r)
    {
        $user = $r->user();
        if (!$user || $user->role !== 'vendor') return response()->json(['message' => 'Forbidden.'], 403);
        $vendorId = $user->vendor_id;

        $data = $r->validate([
            'shop_id' => ['nullable','integer'],
            'base_fee' => ['required','numeric','min:0'],
            'fee_per_km' => ['required','numeric','min:0'],
            'is_active' => ['nullable','boolean'],
        ]);

        $row = VendorDeliveryPrice::updateOrCreate(
            ['vendor_id' => $vendorId, 'shop_id' => $data['shop_id'] ?? null],
            [
                'base_fee' => $data['base_fee'],
                'fee_per_km' => $data['fee_per_km'],
                'is_active' => $data['is_active'] ?? true,
            ]
        );

        return response()->json($row);
    }
}
