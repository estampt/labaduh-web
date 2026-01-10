<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
class VendorController extends Controller
{
    public function index(Request $r) {
        return Vendor::where('approval_status','approved')->where('is_active', true)->with(['shops' => fn($q) => $q->where('is_active', true)])->get();
    }
    public function show(Vendor $vendor) {
        return $vendor->load(['shops' => fn($q) => $q->where('is_active', true), 'services']);
    }
    public function update(Request $r, Vendor $vendor) { $vendor->update($r->all()); return $vendor; }
}
