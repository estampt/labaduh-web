<?php

namespace App\Jobs;

use App\Models\OrderBroadcast;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOrderBroadcastPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $broadcastId) {}

    public function handle(PushNotificationService $push): void
    {

         \Log::info('JOB START SendOrderBroadcastPushJob', ['broadcast_id' => $this->broadcastId]);


        $b = OrderBroadcast::find($this->broadcastId);
        if (!$b) return;

        // Only pending broadcasts
        if ($b->status !== 'pending') {
                \Log::info('JOB FOUND broadcast', ['id' => $b->id, 'status' => $b->status]);
            return;
        }

        // ✅ Direct vendor → user mapping
        $vendorUser = User::where('vendor_id', $b->vendor_id)->first();

        if (!$vendorUser) {
            \Log::warning('Broadcast push: vendor user not found', [
                'broadcast_id' => $b->id,
                'vendor_id' => $b->vendor_id,
            ]);
            return;
        }

        \Log::info('Broadcast push sending', [
            'broadcast_id' => $b->id,
            'order_id' => $b->order_id,
            'vendor_user_id' => $vendorUser->id,
        ]);

        $push->sendToUser(
            $vendorUser->id,
            'New Laundry Order',
            'You received a new order request. Tap to view.',
            [
                'type' => 'order_broadcast',
                'order_id' => $b->order_id,
                'order_broadcast_id' => $b->id,
                'shop_id' => $b->shop_id,
            ]
        );

         $ok = $b->forceFill(['status' => 'sent'])->save();  // ✅ important change
        \Log::info('JOB UPDATE status->sent', ['id' => $b->id, 'ok' => $ok, 'status_now' => $b->fresh()->status]);

        // Mark sent
        //$b->update(['status' => 'sent']);
    }
}
