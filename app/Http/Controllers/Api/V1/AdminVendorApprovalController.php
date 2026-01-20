<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminVendorApprovalController extends Controller
{
    public function pending(Request $request)
    {
        $q = Vendor::query()
            ->where('approval_status', 'pending')
            ->latest();

        if ($search = trim((string) $request->query('search', ''))) {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")
                   ->orWhere('email', 'like', "%{$search}%")
                   ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'data' => $q->paginate($request->integer('per_page', 20)),
        ]);
    }

    public function approve(Request $request, Vendor $vendor)
    {
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

        $vendor->approved_at = Carbon::now();
        $vendor->approved_by = $request->user()?->id;

        // clear rejection info
        $vendor->rejected_at = null;
        $vendor->rejected_by = null;
        $vendor->rejection_reason = null;

        $vendor->save();

        return response()->json([
            'message' => 'Vendor approved.',
            'data' => $vendor,
        ]);
    }

    public function reject(Request $request, Vendor $vendor)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $vendor->approval_status = 'rejected';
        $vendor->is_active = false;

        $vendor->rejected_at = Carbon::now();
        $vendor->rejected_by = $request->user()?->id;
        $vendor->rejection_reason = $data['reason'];

        // optional: clear approval info
        $vendor->approved_at = null;
        $vendor->approved_by = null;

        $vendor->save();

        return response()->json([
            'message' => 'Vendor rejected.',
            'data' => $vendor,
        ]);
    }
}
