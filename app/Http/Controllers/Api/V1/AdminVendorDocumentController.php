<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminVendorDocumentController extends Controller
{
    private const REQUIRED_TYPES = [
        'business_registration',
        'government_id',
    ];

    public function listForVendor(Vendor $vendor)
    {
        return VendorDocument::where('vendor_id', $vendor->id)->orderBy('document_type')->get();
    }

    public function approve(Request $request, VendorDocument $document)
    {
        return DB::transaction(function () use ($request, $document) {
            $document->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->refreshVendorVerifiedAt($document->vendor_id);

            return $document->fresh();
        });
    }

    public function reject(Request $request, VendorDocument $document)
    {
        $data = $request->validate(['rejection_reason' => ['required','string','max:255']]);

        return DB::transaction(function () use ($request, $document, $data) {
            $document->update([
                'status' => 'rejected',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => $data['rejection_reason'],
            ]);

            // If any required doc is rejected, clear verified timestamp
            Vendor::where('id', $document->vendor_id)->update(['documents_verified_at' => null]);

            return $document->fresh();
        });
    }

    private function refreshVendorVerifiedAt(int $vendorId): void
    {
        $vendor = Vendor::find($vendorId);
        if (!$vendor) return;

        $approvedRequired = VendorDocument::where('vendor_id', $vendorId)
            ->whereIn('document_type', self::REQUIRED_TYPES)
            ->where('status', 'approved')
            ->count();

        if ($approvedRequired === count(self::REQUIRED_TYPES)) {
            $vendor->update(['documents_verified_at' => now()]);
        } else {
            $vendor->update(['documents_verified_at' => null]);
        }
    }
}
