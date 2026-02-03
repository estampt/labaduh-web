<?php
// app/Services/OrderTimelineService.php

namespace App\Services;

use App\Models\Order;
use App\Support\OrderTimelineKeys;

class OrderTimelineService
{
    public function forCustomer(Order $order): array
    {
        $steps = OrderTimelineKeys::all();

        // remove delivery steps for walk-in
        if ($order->delivery_mode === 'walk_in') {
            $steps = array_values(array_diff($steps, [
                OrderTimelineKeys::OUT_FOR_DELIVERY,
                OrderTimelineKeys::DELIVERED,
            ]));
        }

        // events keyed by step key
        $events = $order->timelineEvents?->keyBy('key') ?? collect();

        // Determine current:
        // - If there are events, current = last event key in canonical steps.
        // - Else current = order_created (but we recommend recording it on create).
        $currentKey = OrderTimelineKeys::ORDER_CREATED;

        if ($events->isNotEmpty()) {
            // keep only keys that exist in $steps (in case of legacy/typo)
            $validKeys = $events->keys()->filter(fn($k) => in_array($k, $steps, true))->values();
            if ($validKeys->isNotEmpty()) {
                $currentKey = $validKeys->last();
            }
        }

        // Build states:
        // - done: step has event and is before current
        // - current: currentKey
        // - todo: everything after current
        $result = [];
        $foundCurrent = false;

        foreach ($steps as $key) {
            if ($key === $currentKey) {
                $state = 'current';
                $foundCurrent = true;
            } else if (!$foundCurrent) {
                // only mark done if event exists for this step
                $state = $events->has($key) ? 'done' : 'todo';
            } else {
                $state = 'todo';
            }

            $result[] = [
                'key'   => $key,
                'label' => $this->label($key, $order),
                'state' => $state,
                'at'    => $events->has($key) ? optional($events->get($key)->at)->toIso8601String() : null,
            ];
        }

        return [
            'current' => $currentKey,
            'steps'   => $result,
            'flags'   => [
                'requires_customer_action' => $this->needsApproval($order),
                'delivery_mode' => $order->delivery_mode,
                'pricing_status' => $order->pricing_status,
            ],
        ];
    }

    private function label(string $key, Order $order): string
    {
        return match ($key) {
            OrderTimelineKeys::ORDER_CREATED    => 'Order created',
            OrderTimelineKeys::PICKUP_SCHEDULED => 'Pickup scheduled',
            OrderTimelineKeys::PICKED_UP        => 'Picked up',
            OrderTimelineKeys::WASHING          => 'Washing',
            OrderTimelineKeys::READY            => $order->delivery_mode === 'walk_in'
                ? 'Ready for pickup'
                : 'Ready',
            OrderTimelineKeys::OUT_FOR_DELIVERY => 'Out for delivery',
            OrderTimelineKeys::DELIVERED        => 'Delivered',
            OrderTimelineKeys::COMPLETED        => 'Completed',
            default => $key,
        };
    }

    private function needsApproval(Order $order): bool
    {
        return !empty($order->final_proposed_at)
            && empty($order->approved_at);
    }
}
