<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminVendorApprovalController extends Controller
{
    // GET /admin/vendors


   public function index(Request $request)
    {
        $q = Vendor::query()
            ->with(['user:id,vendor_id,name,email,contact_number,address_line1,address_line2'])
            ->latest();

        if ($status = $request->query('status')) {
            $q->where('approval_status', $status);
        }

        if (($active = $request->query('is_active')) !== null) {
            $q->where('is_active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $q->where(function ($qq) use ($search) {
                // vendor fields
                $qq->where('name', 'like', "%{$search}%")
                   ->orWhere('email', 'like', "%{$search}%")
                   ->orWhere('phone', 'like', "%{$search}%")

                   // user fields (via users.vendor_id)
                   ->orWhereHas('user', function ($uq) use ($search) {
                       $uq->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%")
                          ->orWhere('contact_number', 'like', "%{$search}%")
                          ->orWhere('address_line1', 'like', "%{$search}%")
                          ->orWhere('address_line2', 'like', "%{$search}%");
                   });
            });
        }

        $paginated = $q->paginate($request->integer('per_page', 20));

        // Flatten for Flutter, and make "name" = users.name
        $paginated->getCollection()->transform(function ($vendor) {
            $vendor->vendor_name = $vendor->name;              // vendors.name saved
            $vendor->name = $vendor->user->name ?? null;       // ✅ users.name as "name"

            $vendor->email = $vendor->user->email ?? null;
            $vendor->contact_number = $vendor->user->contact_number ?? null;
            $vendor->address_line1 = $vendor->user->address_line1 ?? null;
            $vendor->address_line2 = $vendor->user->address_line2 ?? null;

            return $vendor;
        });

        return response()->json(['data' => $paginated]);
    }



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
