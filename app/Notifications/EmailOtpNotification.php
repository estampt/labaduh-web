<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class EmailOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $otp,
        public readonly int $minutesValid = 5
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Labaduh Verification Code')
            ->greeting('Hi ' . ($notifiable->name ?? ''))
            ->line('Your verification code is:')
            ->line('**' . $this->otp . '**')
            ->line('This code expires in ' . $this->minutesValid . ' minutes.')
            ->line('If you did not request this, you can ignore this email.');
    }
}
