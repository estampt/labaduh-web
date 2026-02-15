<?php

namespace App\Services;

use App\Jobs\SendJobOfferPushJob;
use App\Models\Vendor;
use App\Models\JobOffer;
use App\Models\JobRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OrderBroadcastService
{
    public function __construct(
        private VendorMatchingService $matching,
        private PushNotificationService $push,
    ) {}

    /**
     * Broadcast = create job_offers + schedule FCM pushes.
     */
    public function broadcast(JobRequest $jr, int $topN = 5, int $ttlSeconds = 90): array
    {
        $jr->update(['assignment_status' => 'broadcasting']);

        $matches = $jr->match_snapshot;

        if (!$matches || !is_array($matches)) {
            $matches = $this->matching->match([
                'pickup_lat' => $jr->pickup_lat,
                'pickup_lng' => $jr->pickup_lng,
                'pickup_date' => $jr->pickup_date->format('Y-m-d'),
                'pickup_time_start' => $jr->pickup_time_start,
                'pickup_time_end' => $jr->pickup_time_end,
                'delivery_date' => $jr->delivery_date->format('Y-m-d'),
                'delivery_time_start' => $jr->delivery_time_start,
                'delivery_time_end' => $jr->delivery_time_end,
                'estimated_kg' => (float) $jr->estimated_kg,
            ], 10);

            $jr->update(['match_snapshot' => $matches]);
        }

        $expiresAt = Carbon::now()->addSeconds($ttlSeconds);

        return DB::transaction(function () use ($jr, $matches, $topN, $expiresAt) {

            // Cancel old offers before rebroadcast
            JobOffer::where('job_request_id', $jr->id)
                ->whereIn('status', ['sent', 'seen'])
                ->update(['status' => 'cancelled']);

            $created = [];

            foreach (array_slice($matches, 0, $topN) as $m) {

                $offer = JobOffer::updateOrCreate(
                    [
                        'job_request_id' => $jr->id,
                        'vendor_id'      => $m['vendor_id'],
                    ],
                    [
                        'shop_id'    => $m['shop_id'],
                        'status'     => 'sent',
                        'expires_at' => $expiresAt,
                    ]
                );

                $created[] = $offer;
            }

            // After DB commit → schedule pushes
            DB::afterCommit(function () use ($created) {

                if (!filter_var(env('BROADCAST_PUSH_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
                    return;
                }

                $freeDelay    = (int) env('VENDOR_PUSH_DELAY_FREE_SECONDS', 300);
                $premiumDelay = (int) env('VENDOR_PUSH_DELAY_PREMIUM_SECONDS', 0);

                foreach ($created as $offer) {

                    // Only push if still active
                    if ($offer->status !== 'sent') {
                        continue;
                    }

                    $tier = Vendor::query()
                        ->where('id', (int) $offer->vendor_id)
                        ->value('subscription_tier');

                    $tier = strtoupper((string) ($tier ?? 'FREE'));

                    $delaySeconds = ($tier === 'premium')
                        ? $premiumDelay
                        : $freeDelay;

                    SendJobOfferPushJob::dispatch((int) $offer->id)
                        ->delay(now()->addSeconds($delaySeconds));
                }
            });

            return $created;
        });
    }
}
