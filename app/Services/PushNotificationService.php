<?php

namespace App\Services;

use App\Models\PushToken;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class PushNotificationService
{
    private Messaging $messaging;

    public function __construct()
    {
        $path = base_path(env('FIREBASE_CREDENTIALS'));

        $this->messaging = (new Factory)
            ->withServiceAccount($path)
            ->createMessaging();
    }

    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $tokens = PushToken::where('user_id', $userId)->pluck('token')->values()->all();
        if (empty($tokens)) return;

        $this->sendToTokens($tokens, $title, $body, $data);
    }

    public function sendToRole(string $role, string $title, string $body, array $data = []): void
    {
        $tokens = DB::table('push_tokens')
            ->join('users', 'push_tokens.user_id', '=', 'users.id')
            ->where('users.role', $role)
            ->pluck('push_tokens.token')
            ->values()
            ->all();

        if (empty($tokens)) return;

        $this->sendToTokens($tokens, $title, $body, $data);
    }

    public function sendToAll(string $title, string $body, array $data = []): void
    {
        $tokens = PushToken::query()->pluck('token')->values()->all();
        if (empty($tokens)) return;

        $this->sendToTokens($tokens, $title, $body, $data);
    }

    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): void
    {
        $notification = Notification::create($title, $body);

        foreach (array_chunk($tokens, 500) as $chunk) {
            $message = CloudMessage::newest()
                ->withNotification($notification)
                ->withData($this->stringifyData($data));

            $this->messaging->sendMulticast($message, $chunk);
        }
    }

    private function stringifyData(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $out[$k] = is_string($v) ? $v : json_encode($v);
        }
        return $out;
    }
}
