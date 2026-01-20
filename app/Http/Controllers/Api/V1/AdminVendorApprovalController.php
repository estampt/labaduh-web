<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminVendorApprovalController extends Controller
{
    // GET /admin/vendors/pending
    public function pending(Request $request)
    {
        $q = Vendor::query()
            ->where('approval_status', 'pending')
            ->latest();

        return response()->json([
            'data' => $q->paginate($request->integer('per_page', 20)),
        ]);
    }

    // PATCH /admin/vendors/{vendor}/approve
    public function approve(Request $request, Vendor $vendor)
    {
        // Require all docs approved (only if relation exists on Vendor)
        if (method_exists($vendor, 'documents')) {
            $blocked = $vendor->documents()
                ->whereIn('status', ['pending', 'rejected'])
                ->exists();

            if ($blocked) {
                return response()->json([
                    'message' => 'Cannot approve vendor: some documents are still pending or rejected.',
                ], 422);
            }
        }

        $vendor->approval_status = 'approved';
        $vendor->is_active = true;

        // If you have these columns; if not, remove them:
        if (isset($vendor->approved_at)) $vendor->approved_at = Carbon::now();
        if (isset($vendor->approved_by)) $vendor->approved_by = $request->user()?->id;

        $vendor->save();

        return response()->json([
            'message' => 'Vendor approved.',
            'data' => $vendor,
        ]);
    }

    // PATCH /admin/vendors/{vendor}/reject
    public function reject(Request $request, Vendor $vendor)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $vendor->approval_status = 'rejected';
        $vendor->is_active = false;

        // If you have these columns; if not, remove them:
        if (isset($vendor->rejected_at)) $vendor->rejected_at = Carbon::now();
        if (isset($vendor->rejected_by)) $vendor->rejected_by = $request->user()?->id;
        if (isset($vendor->rejection_reason)) $vendor->rejection_reason = $data['reason'];

        $vendor->save();

        return response()->json([
            'message' => 'Vendor rejected.',
            'data' => $vendor,
        ]);
    }
}
