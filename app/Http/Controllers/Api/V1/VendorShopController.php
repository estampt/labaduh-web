<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorShop;
use Illuminate\Http\Request;
class VendorShopController extends Controller
{
    public function index(Vendor $vendor) { if(!$vendor->isApproved()) return []; return $vendor->shops()->where('is_active', true)->get(); }
    public function store(Request $r, Vendor $vendor) {
        $data = $r->validate(['name'=>['required','string','max:255'],'phone'=>['nullable','string','max:50'],'address'=>['nullable','string','max:255'],'latitude'=>['nullable','numeric'],'longitude'=>['nullable','numeric'],'service_radius_km'=>['nullable','numeric','min:0'],'country_id'=>['nullable','integer'],'state_province_id'=>['nullable','integer'],'city_id'=>['nullable','integer'],'is_active'=>['sometimes','boolean']]);
        $data['vendor_id']=$vendor->id; return VendorShop::create($data);
    }
    public function update(Request $r, Vendor $vendor, VendorShop $shop) {
        $data = $r->validate(['name'=>['sometimes','string','max:255'],'phone'=>['nullable','string','max:50'],'address'=>['nullable','string','max:255'],'latitude'=>['nullable','numeric'],'longitude'=>['nullable','numeric'],'service_radius_km'=>['nullable','numeric','min:0'],'country_id'=>['nullable','integer'],'state_province_id'=>['nullable','integer'],'city_id'=>['nullable','integer'],'is_active'=>['sometimes','boolean']]);
        $shop->update($data); return $shop->fresh();
    }
    public function toggle(Vendor $vendor, VendorShop $shop) { $shop->update(['is_active'=>!$shop->is_active]); return $shop->fresh(); }
}
