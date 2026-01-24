<?php


namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;

use App\Models\Service;              // master services table
use App\Models\Vendor;
use App\Models\VendorShop;
use App\Models\VendorServicePrice;
use Illuminate\Http\Request;

class VendorShopServicePriceController extends Controller
{
    public function index(Request $r, Vendor $vendor, VendorShop $shop)
    {
        $q = VendorServicePrice::query()
            ->where('vendor_id', $vendor->id)
            ->where('shop_id', $shop->id);

        if ($r->filled('service_id')) $q->where('service_id', $r->integer('service_id'));
        if ($r->filled('is_active')) $q->where('is_active', (bool)$r->boolean('is_active'));

        return response()->json([
            'data' => $q->latest('id')->paginate($r->integer('per_page', 50)),
        ]);
    }

    public function store(Request $r, Vendor $vendor, VendorShop $shop)
    {
        $data = $this->validatePrice($r);

        // ✅ ensure the master service exists
        $exists = Service::where('id', $data['service_id'])->exists();
        if (!$exists) abort(422, 'Invalid service_id.');

        $data['vendor_id'] = $vendor->id;
        $data['shop_id'] = $shop->id;

        $price = VendorServicePrice::create($data);

        return response()->json(['data' => $price], 201);
    }

    public function update(Request $r, Vendor $vendor, VendorShop $shop, VendorServicePrice $price)
    {
        $this->assertBelongsToShop($vendor, $shop, $price);

        $data = $this->validatePrice($r, isUpdate: true);

        // If service_id is being changed, validate it exists
        if (array_key_exists('service_id', $data)) {
            $exists = Service::where('id', $data['service_id'])->exists();
            if (!$exists) abort(422, 'Invalid service_id.');
        }

        $price->update($data);

        return response()->json(['data' => $price->fresh()]);
    }

    public function destroy(Vendor $vendor, VendorShop $shop, VendorServicePrice $price)
    {
        $this->assertBelongsToShop($vendor, $shop, $price);
        $price->delete();

        return response()->json(['ok' => true]);
    }

    public function toggle(Vendor $vendor, VendorShop $shop, VendorServicePrice $price)
    {
        $this->assertBelongsToShop($vendor, $shop, $price);

        $price->is_active = !$price->is_active;
        $price->save();

        return response()->json(['data' => $price->fresh()]);
    }

    // ✅ UI helper: show all master services + existing price for this shop
    public function servicesWithPrices(Vendor $vendor, VendorShop $shop)
    {
        $services = Service::query()->orderBy('name')->get();

        $prices = VendorServicePrice::query()
            ->where('vendor_id', $vendor->id)
            ->where('shop_id', $shop->id)
            ->get()
            ->keyBy('service_id');

        $rows = $services->map(function ($s) use ($prices) {
            return [
                'service' => $s,
                'price' => $prices->get($s->id), // null if not priced yet
            ];
        });

        return response()->json(['data' => $rows]);
    }

    private function validatePrice(Request $r, bool $isUpdate = false): array
    {
        $req = $isUpdate ? 'sometimes' : 'required';

        return $r->validate([
            'service_id' => [$req, 'integer'],

            'category_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'pricing_model' => [$req, 'in:per_kg_min,per_block,flat'],

            'min_kg' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'rate_per_kg' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'block_kg' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'block_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'flat_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],

            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function assertBelongsToShop(Vendor $vendor, VendorShop $shop, VendorServicePrice $price): void
    {
        if ((int)$price->vendor_id !== (int)$vendor->id || (int)$price->shop_id !== (int)$shop->id) {
            abort(404);
        }
    }
}
