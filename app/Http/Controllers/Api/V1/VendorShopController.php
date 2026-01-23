<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorShop;
use Illuminate\Http\Request;
class VendorShopController extends Controller
{
    /*public function index(Vendor $vendor) { if(!$vendor->isApproved()) return []; return $vendor->shops()->where('is_active', true)->get(); }

*/
    public function index(Request $request, Vendor $vendor)
    {
        $q = $vendor->shops()->latest();

        // Optional filters
        if (($active = $request->query('is_active')) !== null) {
            $q->where('is_active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")
                   ->orWhere('contact_number', 'like', "%{$search}%")
                   ->orWhere('address_line1', 'like', "%{$search}%")
                   ->orWhere('address_line2', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'data' => $q->paginate($request->integer('per_page', 20)),
        ]);
    }

    public function store(Request $r, Vendor $vendor)
    {
        $data = $r->validate([
            'name' => ['required','string','max:255'],
            'phone' => ['nullable','string','max:50'],

            // ✅ updated address fields
            'address_line1' => ['nullable','string','max:1000'],
            'address_line2' => ['nullable','string','max:255'],
            'postal_code'   => ['nullable','string','max:50'],

            'latitude' => ['nullable','numeric'],
            'longitude' => ['nullable','numeric'],

            'country_id' => ['nullable','integer'],
            'state_province_id' => ['nullable','integer'],
            'city_id' => ['nullable','integer'],

            'default_max_orders_per_day' => ['nullable','integer','min:0'],
            'default_max_kg_per_day' => ['nullable','numeric','min:0'],

            'is_active' => ['sometimes','boolean'],
        ]);

        $data['vendor_id'] = $vendor->id;

        $shop = VendorShop::create($data);

        return response()->json(['data' => $shop], 201);
    }


    public function update(Request $r, Vendor $vendor, VendorShop $shop)
    {
         \Log::info('UPDATE RAW', $r->all());
        $data = $r->validate([
            'name' => ['sometimes','string','max:255'],
            'phone' => ['nullable','string','max:50'],

            'address_line1' => ['nullable','string','max:1000'],
            'address_line2' => ['nullable','string','max:255'],
            'postal_code' => ['nullable','string','max:50'],

            'country_id' => ['nullable','integer'],
            'state_province_id' => ['nullable','integer'],
            'city_id' => ['nullable','integer'],

            'latitude' => ['nullable','numeric'],
            'longitude' => ['nullable','numeric'],

            'default_max_orders_per_day' => ['nullable','integer','min:0'],
            'default_max_kg_per_day' => ['nullable','numeric','min:0'],

            'is_active' => ['sometimes','boolean'],
        ]);
    \Log::info('UPDATE VALIDATED', $data);

        $shop->update($data);

        return response()->json(['data' => $shop->fresh()]);
    }


    public function uploadPhoto(Request $r, Vendor $vendor, VendorShop $shop)
    {
        $r->validate([
            'photo' => ['required','image','max:5120'], // 5MB
        ]);

        $path = $r->file('photo')->store('vendor-shops', 'public');
        $url = asset('storage/'.$path);

        $shop->update(['profile_photo_url' => $url]);

        return response()->json(['data' => $shop->fresh()]);
    }

    public function toggle(Vendor $vendor, VendorShop $shop) { $shop->update(['is_active'=>!$shop->is_active]); return $shop->fresh(); }
}
