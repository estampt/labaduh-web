<?php

namespace App\Services;

use App\Models\PushToken;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\AndroidConfig;
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
            // NOTE: Kreait supports CloudMessage::new() (or CloudMessage::fromArray() in older versions).
            // CloudMessage::newest() is not a valid constructor in most versions.
            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData($this->stringifyData($data))
                // Better delivery reliability when the app is backgrounded (especially Android)
                ->withAndroidConfig(AndroidConfig::fromArray([
                    'priority' => 'high',
                ]));

            $report = $this->messaging->sendMulticast($message, $chunk);

            // Strongly recommended: prune invalid/unregistered tokens to prevent token rot.
            // Wrapped in try/catch to remain compatible across firebase-php versions.
            try {
                $invalidTokens = [];

                $failures = $report->failures();
                $items = method_exists($failures, 'getItems') ? $failures->getItems() : $failures;

                foreach ($items as $failure) {
                    if (!method_exists($failure, 'target')) {
                        continue;
                    }

                    $target = $failure->target();
                    $token = method_exists($target, 'value') ? $target->value() : (string) $target;
                    if ($token !== '') {
                        $invalidTokens[] = $token;
                    }
                }

                if (!empty($invalidTokens)) {
                    PushToken::whereIn('token', array_values(array_unique($invalidTokens)))->delete();
                }
            } catch (\Throwable $e) {
                // Don't break app flow if report parsing differs across versions.
            }
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
