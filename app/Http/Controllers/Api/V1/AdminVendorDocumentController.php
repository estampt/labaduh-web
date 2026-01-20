<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminVendorDocumentController extends Controller
{
    // GET /admin/vendors/{vendor}/documents
    public function listForVendor(Vendor $vendor)
    {
        $docs = VendorDocument::query()
            ->where('vendor_id', $vendor->id)
            ->latest()
            ->get();

        return response()->json([
            'vendor_id' => $vendor->id,
            'data' => $docs,
        ]);
    }

    // PATCH /admin/vendor-documents/{document}/approve
    public function approve(Request $request, VendorDocument $document)
    {
        $document->status = 'approved';
        $document->reviewed_at = Carbon::now();
        $document->reviewed_by = $request->user()?->id;
        $document->rejection_reason = null;
        $document->save();

        return response()->json([
            'message' => 'Document approved.',
            'data' => $document,
        ]);
    }

    // PATCH /admin/vendor-documents/{document}/reject
    public function reject(Request $request, VendorDocument $document)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $document->status = 'rejected';
        $document->reviewed_at = Carbon::now();
        $document->reviewed_by = $request->user()?->id;
        $document->rejection_reason = $data['reason'];
        $document->save();

        return response()->json([
            'message' => 'Document rejected.',
            'data' => $document,
        ]);
    }
}
