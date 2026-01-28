<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;

use App\Models\Vendor;
use App\Models\VendorShop;
use App\Models\VendorServiceOptionPrice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorServiceOptionPriceController extends Controller
{
    public function index(Request $r, Vendor $vendor, VendorShop $shop)
    {

        $q = VendorServiceOptionPrice::query()
            ->where('vendor_id', $vendor->id)
            ->where('shop_id', $shop->id)
            ->with([
                'serviceOption:id,kind,name,description,price,price_type,is_active' // adjust columns to your table
            ]);

        if ($r->filled('active')) {
            $q->where('is_active', filter_var($r->active, FILTER_VALIDATE_BOOLEAN));
        }
        return response()->json([
            'data' => $q->orderByDesc('id')->paginate((int) ($r->per_page ?? 50)),
        ]);
    }



    public function store(Request $r, Vendor $vendor, VendorShop $shop)
    {
        // ✅ single validation only
        $data = $r->validate([
            'vendor_service_price_id' => ['required', 'integer', Rule::exists('vendor_service_prices', 'id')],
            'service_option_id'       => ['required', 'integer', Rule::exists('service_options', 'id')],
            'price'                   => ['nullable', 'numeric', 'min:0'],
            'price_type'              => ['nullable', Rule::in(['fixed', 'per_kg', 'per_item'])],
            'is_active'               => ['sometimes', 'boolean'],
        ]);

        $data['vendor_id'] = $vendor->id;
        $data['shop_id']   = $shop->id;

        // ✅ If your DB unique is (vendor_id, shop_id, service_option_id) keep it like this
        // But since you added vendor_service_price_id FK, it's usually better to include it
        // in the upsert key to avoid collisions across different service prices.
        $row = VendorServiceOptionPrice::updateOrCreate(
            [
                'vendor_id'               => $vendor->id,
                'shop_id'                 => $shop->id,
                'vendor_service_price_id' => $data['vendor_service_price_id'],
                'service_option_id'       => $data['service_option_id'],
            ],
            $data
        );

        return response()->json(['data' => $row->fresh()]);
    }

    public function update(Request $r, Vendor $vendor, VendorShop $shop, VendorServiceOptionPrice $optionPrice)
    {
        abort_unless((int) $optionPrice->vendor_id === (int) $vendor->id, 404);
        abort_unless((int) $optionPrice->shop_id === (int) $shop->id, 404);

        $data = $r->validate([
            'price'      => ['nullable', 'numeric', 'min:0'],
            'price_type' => ['nullable', Rule::in(['fixed', 'per_kg', 'per_item'])],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        $optionPrice->update($data);

        return response()->json(['data' => $optionPrice->fresh()]);
    }

    public function destroy(Vendor $vendor, VendorShop $shop, VendorServiceOptionPrice $optionPrice)
    {
        abort_unless((int) $optionPrice->vendor_id === (int) $vendor->id, 404);
        abort_unless((int) $optionPrice->shop_id === (int) $shop->id, 404);

        $optionPrice->delete();

        return response()->json(['ok' => true]);
    }
}
