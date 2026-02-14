<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderUpdatedNotification extends Notification
{
    use Queueable;

    /**
     * @param string $event A short event key (e.g. status_changed, driver_assigned)
     * @param array  $payload Flexible payload for UI (kept in DB + sent as push data)
     *                        Recommended to include 'message' for push body.
     */
    public function __construct(
        public int $orderId,
        public string $event,
        public array $payload = [],
    ) {
    }

    public function via($notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'kind' => 'order',
            'order_id' => $this->orderId,
            'event' => $this->event,
            'payload' => $this->payload,
        ];
    }

    public function toFcm($notifiable): array
    {
        $title = (string) ($this->payload['title'] ?? 'Order Update');
        $body  = (string) ($this->payload['message'] ?? "Order #{$this->orderId} updated");

        // Flatten a minimal data payload (FCM data must be strings; your PushNotificationService stringifies)
        $data = $this->payload['data'] ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        return [
            'title' => $title,
            'body' => $body,
            'data' => array_merge([
                'type' => 'order_update',
                'order_id' => (string) $this->orderId,
                'event' => (string) $this->event,
            ], $data),
        ];
    }
}
