<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MessageReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $threadId,
        public int $senderId,
        public string $senderName,
        public string $textPreview,
    ) {
    }

    public function via($notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'kind' => 'message',
            'thread_id' => $this->threadId,
            'sender_id' => $this->senderId,
            'sender_name' => $this->senderName,
            'text_preview' => $this->textPreview,
        ];
    }

    public function toFcm($notifiable): array
    {
        return [
            'title' => $this->senderName,
            'body' => $this->textPreview,
            'data' => [
                'type' => 'message',
                'thread_id' => (string) $this->threadId,
                'sender_id' => (string) $this->senderId,
            ],
        ];
    }
}
