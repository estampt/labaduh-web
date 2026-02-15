<?php

namespace App\Jobs;

use App\Models\JobOffer;
use App\Models\JobRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendJobOfferPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $jobOfferId) {}

    public function handle(PushNotificationService $push): void
    {
        $offer = JobOffer::query()->find($this->jobOfferId);
        if (!$offer) return;

        // ✅ Offer must still be valid
        if (!in_array($offer->status, ['sent', 'seen'], true)) return;
        if ($offer->expires_at && now()->greaterThan($offer->expires_at)) return;

        // ✅ Find vendor tier (vendors.id = $offer->vendor_id)
        $tier = Vendor::query()
            ->where('id', (int) $offer->vendor_id)
            ->value('subscription_tier');

        $tier = strtoupper((string) $tier);

        // ✅ Premium advantage: skip FREE push if someone already accepted
        $onlyIfUnaccepted = filter_var(env('VENDOR_FREE_PUSH_ONLY_IF_UNACCEPTED', true), FILTER_VALIDATE_BOOLEAN);

        if ($onlyIfUnaccepted && $tier !== 'PREMIUM') {
            // if any offer already accepted, don't notify free tier anymore
            $hasAccepted = JobOffer::query()
                ->where('job_request_id', (int) $offer->job_request_id)
                ->where('status', 'accepted')
                ->exists();

            if ($hasAccepted) return;

            // optional extra safety: if job request already assigned
            $assigned = JobRequest::query()
                ->where('id', (int) $offer->job_request_id)
                ->whereIn('assignment_status', ['assigned', 'completed', 'cancelled'])
                ->exists();

            if ($assigned) return;
        }

        // ✅ Map vendor -> user (users.vendor_id = vendors.id)
        $vendorUser = User::query()
            ->where('vendor_id', (int) $offer->vendor_id)
            ->first();

        if (!$vendorUser) return;

        $push->sendToUser(
            (int) $vendorUser->id,
            'New Laundry Job',
            'You received a new job offer. Tap to view.',
            [
                'type' => 'job_offer',
                'route' => '/v/job-offers',   // ✅ ADD THIS
                'job_request_id' => (int) $offer->job_request_id,
                'job_offer_id' => (int) $offer->id,
            ]
        );

    }
}
