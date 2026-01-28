<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;

class AdminPushTestController extends Controller
{
    public function send(Request $request, PushNotificationService $push)
    {
        $data = $request->validate([
            'target' => ['required', 'in:user,role,all'],
            'user_id' => ['nullable', 'integer'],
            'role' => ['nullable', 'string'],
            'title' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:200'],
            'data' => ['nullable', 'array'],
        ]);

        $payload = $data['data'] ?? [];

        if ($data['target'] === 'user') {
            if (empty($data['user_id'])) {
                return response()->json(['ok' => false, 'message' => 'user_id is required'], 422);
            }
            $push->sendToUser((int)$data['user_id'], $data['title'], $data['body'], $payload);
        }

        if ($data['target'] === 'role') {
            if (empty($data['role'])) {
                return response()->json(['ok' => false, 'message' => 'role is required'], 422);
            }
            $push->sendToRole($data['role'], $data['title'], $data['body'], $payload);
        }

        if ($data['target'] === 'all') {
            $push->sendToAll($data['title'], $data['body'], $payload);
        }

        return response()->json(['ok' => true]);
    }
}
