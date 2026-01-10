<?php

namespace App\Services;

use App\Models\JobOffer;
use App\Models\JobRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OrderBroadcastService
{
    public function __construct(private VendorMatchingService $matching) {}

    /**
     * Broadcast to top N vendors. Uses match_snapshot if present, otherwise recompute.
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
                'estimated_kg' => (float)$jr->estimated_kg,
            ], 10);
            $jr->update(['match_snapshot' => $matches]);
        }

        $expiresAt = Carbon::now()->addSeconds($ttlSeconds);

        return DB::transaction(function () use ($jr, $matches, $topN, $expiresAt) {
            $created = [];
            foreach (array_slice($matches, 0, $topN) as $m) {
                $created[] = JobOffer::updateOrCreate(
                    ['job_request_id' => $jr->id, 'vendor_id' => $m['vendor_id']],
                    ['shop_id' => $m['shop_id'], 'status' => 'sent', 'expires_at' => $expiresAt]
                );
            }
            return $created;
        });
    }
}
