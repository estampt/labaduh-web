<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorReviewController extends Controller
{
    public function index(Vendor $vendor)
    {
        if (!$vendor->isApproved()) return [];

        return VendorReview::query()
            ->where('vendor_id', $vendor->id)
            ->where('is_visible', true)
            ->orderByDesc('id')
            ->paginate(20);
    }

    public function storeForOrder(Request $request, Order $order)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        if ($user->role !== 'customer') return response()->json(['message' => 'Only customers can review.'], 403);

        if ($order->status !== 'completed') {
            return response()->json(['message' => 'Order must be completed before review.'], 422);
        }

        $data = $request->validate([
            'rating' => ['required','integer','min:1','max:5'],
            'comment' => ['nullable','string','max:2000'],
        ]);

        return DB::transaction(function () use ($data, $order, $user) {

            $existing = VendorReview::where('order_id', $order->id)->first();
            if ($existing) {
                return response()->json(['message' => 'This order was already reviewed.'], 409);
            }

            $review = VendorReview::create([
                'vendor_id' => $order->vendor_id,
                'customer_id' => $user->id,
                'order_id' => $order->id,
                'rating' => (int)$data['rating'],
                'comment' => $data['comment'] ?? null,
                'is_visible' => true,
            ]);

            $vendor = Vendor::findOrFail($order->vendor_id);

            $newCount = (int)$vendor->rating_count + 1;
            $newAvg = (($vendor->rating_avg * $vendor->rating_count) + $review->rating) / max(1, $newCount);

            $vendor->update([
                'rating_count' => $newCount,
                'rating_avg' => round($newAvg, 2),
            ]);

            return $review->load('customer');
        });
    }
}
