<?php
// app/Services/OrderTimelineRecorder.php

namespace App\Services;

use App\Models\Order;

class OrderTimelineRecorder
{
    public function record(
        Order $order,
        string $key,
        ?string $actorType = null,
        ?int $actorId = null,
        array $meta = []
    ): void {
        // prevent duplicates
        $exists = $order->timelineEvents()
            ->where('key', $key)
            ->exists();

        if ($exists) return;

        $order->timelineEvents()->create([
            'key' => $key,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'meta' => empty($meta) ? null : $meta,
        ]);
    }
}
