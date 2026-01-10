<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JobOffer;
use App\Models\Vendor;
use App\Services\VendorAcceptanceService;
use Illuminate\Http\Request;

class VendorJobController extends Controller
{
    public function index(Request $r)
    {
        $user = $r->user();
        if (!$user || $user->role !== 'vendor') return response()->json(['message' => 'Forbidden.'], 403);
        $vendorId = $user->vendor_id;

        $offers = JobOffer::query()
            ->where('vendor_id', $vendorId)
            ->orderByDesc('id')
            ->paginate(20);

        return $offers;
    }

    public function accept(JobOffer $offer, Request $r, VendorAcceptanceService $svc)
    {
        $user = $r->user();
        if (!$user || $user->role !== 'vendor') return response()->json(['message' => 'Forbidden.'], 403);

        $vendor = Vendor::findOrFail($user->vendor_id);
        if ($offer->vendor_id !== $vendor->id) return response()->json(['message' => 'Forbidden.'], 403);

        $res = $svc->accept($offer, $vendor);
        if (!$res['ok']) return response()->json(['message' => $res['message']], 422);

        return response()->json($res['order'], 201);
    }

    public function reject(JobOffer $offer, Request $r, VendorAcceptanceService $svc)
    {
        $user = $r->user();
        if (!$user || $user->role !== 'vendor') return response()->json(['message' => 'Forbidden.'], 403);
        $vendor = Vendor::findOrFail($user->vendor_id);
        if ($offer->vendor_id !== $vendor->id) return response()->json(['message' => 'Forbidden.'], 403);

        $data = $r->validate(['reason' => ['nullable','string','max:255']]);
        $res = $svc->reject($offer, $vendor, $data['reason'] ?? null);
        return response()->json($res);
    }
}
