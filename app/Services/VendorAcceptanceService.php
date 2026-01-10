<?php

namespace App\Services;

use App\Models\JobOffer;
use App\Models\JobRequest;
use App\Models\Vendor;
use App\Models\VendorJobResponse;
use App\Models\Order;
use App\Models\Delivery;
use App\Models\VendorShop;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VendorAcceptanceService
{
    public function __construct(private CapacityService $capacity) {}

    public function accept(JobOffer $offer, Vendor $vendor): array
    {
        return DB::transaction(function () use ($offer, $vendor) {
            $offer->refresh();

            if ($offer->status !== 'sent' && $offer->status !== 'seen') {
                return ['ok' => false, 'message' => 'Offer not available.'];
            }
            if ($offer->expires_at && Carbon::now()->greaterThan($offer->expires_at)) {
                $offer->update(['status' => 'expired']);
                return ['ok' => false, 'message' => 'Offer expired.'];
            }

            $jr = JobRequest::lockForUpdate()->findOrFail($offer->job_request_id);
            if ($jr->assignment_status === 'assigned') {
                $offer->update(['status' => 'cancelled']);
                return ['ok' => false, 'message' => 'Already assigned.'];
            }

            $shop = VendorShop::where('id', $offer->shop_id)->where('vendor_id', $vendor->id)->firstOrFail();

            // Hard reserve capacity now
            if (!$this->capacity->canReserve($shop, $jr->pickup_date->format('Y-m-d'), (float)$jr->estimated_kg)) {
                $offer->update(['status' => 'rejected']);
                VendorJobResponse::create([
                    'job_offer_id' => $offer->id,
                    'vendor_id' => $vendor->id,
                    'response' => 'rejected',
                    'reason' => 'Capacity exceeded'
                ]);
                return ['ok' => false, 'message' => 'Capacity exceeded.'];
            }

            $this->capacity->reserve($shop, $jr->pickup_date->format('Y-m-d'), (float)$jr->estimated_kg);

            // Mark assigned
            $jr->update([
                'assignment_status' => 'assigned',
                'assigned_vendor_id' => $vendor->id,
                'assigned_shop_id' => $shop->id,
            ]);

            // Accept the offer; cancel other offers
            $offer->update(['status' => 'accepted']);
            JobOffer::where('job_request_id', $jr->id)
                ->where('id', '!=', $offer->id)
                ->update(['status' => 'cancelled']);

            VendorJobResponse::create([
                'job_offer_id' => $offer->id,
                'vendor_id' => $vendor->id,
                'response' => 'accepted',
            ]);

            // Create real order (keeps existing order schema intact)
            /** @var Order $order */
            $order = Order::create([
                'job_request_id' => $jr->id,
                'vendor_id' => $vendor->id,
                'shop_id' => $shop->id,
                'customer_id' => $jr->customer_id,

                'pickup_lat' => $jr->pickup_lat,
                'pickup_lng' => $jr->pickup_lng,
                'dropoff_lat' => $jr->dropoff_lat,
                'dropoff_lng' => $jr->dropoff_lng,

                'pickup_date' => $jr->pickup_date,
                'pickup_time_start' => $jr->pickup_time_start,
                'pickup_time_end' => $jr->pickup_time_end,

                'delivery_date' => $jr->delivery_date,
                'delivery_time_start' => $jr->delivery_time_start,
                'delivery_time_end' => $jr->delivery_time_end,

                'status' => 'pending',
                'notes' => $jr->notes,
            ]);

            // Create deliveries (pickup + dropoff) placeholders
            Delivery::create([
                'order_id' => $order->id,
                'vendor_id' => $vendor->id,
                'shop_id' => $shop->id,
                'type' => 'pickup',
                'status' => 'pending',
                'scheduled_at' => null,
                'pickup_lat' => $jr->pickup_lat,
                'pickup_lng' => $jr->pickup_lng,
                'dropoff_lat' => $shop->latitude,
                'dropoff_lng' => $shop->longitude,
            ]);

            Delivery::create([
                'order_id' => $order->id,
                'vendor_id' => $vendor->id,
                'shop_id' => $shop->id,
                'type' => 'dropoff',
                'status' => 'pending',
                'scheduled_at' => null,
                'pickup_lat' => $shop->latitude,
                'pickup_lng' => $shop->longitude,
                'dropoff_lat' => $jr->dropoff_lat,
                'dropoff_lng' => $jr->dropoff_lng,
            ]);

            return ['ok' => true, 'order' => $order];
        });
    }

    public function reject(JobOffer $offer, Vendor $vendor, ?string $reason = null): array
    {
        if (!in_array($offer->status, ['sent','seen'])) {
            return ['ok' => false, 'message' => 'Offer not available.'];
        }
        $offer->update(['status' => 'rejected']);

        VendorJobResponse::create([
            'job_offer_id' => $offer->id,
            'vendor_id' => $vendor->id,
            'response' => 'rejected',
            'reason' => $reason,
        ]);

        return ['ok' => true];
    }
}
