<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorDocument;
use Illuminate\Http\Request;
class AdminVendorApprovalController extends Controller
{
    private const REQUIRED_TYPES = ['business_registration','government_id'];

    public function pending(){ return Vendor::where('approval_status','pending')->orderByDesc('id')->with('shops')->get(); }
    public function approve(Request $r, Vendor $vendor){
        // Require docs before approval
        $approvedRequired = VendorDocument::where('vendor_id', $vendor->id)
            ->whereIn('document_type', self::REQUIRED_TYPES)
            ->where('status', 'approved')
            ->count();

        if ($approvedRequired !== count(self::REQUIRED_TYPES)) {
            return response()->json([
                'message' => 'Vendor has missing or unverified documents',
                'required' => self::REQUIRED_TYPES,
                'approved_required_count' => $approvedRequired,
            ], 422);
        }

        $vendor->update([
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $r->user()->id,
            'is_active' => true,
        ]);

        return $vendor->fresh()->load('shops');
    }
    public function reject(Request $r, Vendor $vendor){ $vendor->update(['approval_status'=>'rejected','approved_at'=>now(),'approved_by'=>$r->user()->id,'is_active'=>false]); return $vendor->fresh()->load('shops'); }
}
