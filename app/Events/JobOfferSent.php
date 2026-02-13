<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobOfferSent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $jobRequestId,
        public int $jobOfferId,
        public int $vendorId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("vendor.{$this->vendorId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'job.offer.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'job_request_id' => $this->jobRequestId,
            'job_offer_id' => $this->jobOfferId,
        ];
    }
}
