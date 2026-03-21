<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatMessageController extends Controller
{
    public function index(MessageThread $thread, Request $request)
    {
        $user = $request->user();

        $allowed = (int) $thread->customer_user_id === (int) $user->id
            || (!empty($thread->vendor_user_id) && (int) $thread->vendor_user_id === (int) $user->id);

        abort_unless($allowed, 403, 'Not allowed.');

        if ($thread->scope === 'order' && !empty($thread->order_id)) {
            $order = Order::query()->find((int) $thread->order_id);

            if ($order) {
                $lockedStatuses = config('chat.order_locked_statuses', [
                    'completed',
                    'cancelled',
                    'canceled',
                ]);

                if (in_array($order->status, $lockedStatuses, true) && $thread->locked_at === null) {
                    $thread->update(['locked_at' => now()]);
                    $thread->refresh();
                }
            }
        }

        $messages = $thread->messages()
            ->orderBy('sent_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'messages' => $messages,
            'thread_locked' => $thread->locked_at !== null,
            'locked_at' => $thread->locked_at,
        ]);
    }

    public function store(MessageThread $thread, Request $request)
    {
        $user = $request->user();

        $allowed = (int) $thread->customer_user_id === (int) $user->id
            || (!empty($thread->vendor_user_id) && (int) $thread->vendor_user_id === (int) $user->id);

        abort_unless($allowed, 403, 'Not allowed.');

        if ($thread->scope === 'order') {
            $order = Order::query()->findOrFail((int) $thread->order_id);

            $lockedStatuses = config('chat.order_locked_statuses', [
                'completed',
                'cancelled',
                'canceled',
            ]);

            if (in_array($order->status, $lockedStatuses, true)) {
                if ($thread->locked_at === null) {
                    $thread->update(['locked_at' => now()]);
                }

                abort(409, 'Chat is closed because this order is already completed or cancelled.');
            }
        }

        if ($thread->isLocked()) {
            abort(409, 'Chat is closed.');
        }

        $payload = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = DB::transaction(function () use ($thread, $user, $payload) {
            $message = Message::query()->create([
                'id' => (string) Str::uuid(),
                'thread_id' => $thread->id,
                'sender_id' => $user->id,
                'shop_id' => $thread->shop_id,
                'body' => $payload['body'],
                'sent_at' => now(),
            ]);

            $thread->update([
                'last_message_at' => now(),
            ]);

            return $message;
        });

        $receiverId = ((int) $user->id === (int) $thread->customer_user_id)
            ? (int) $thread->vendor_user_id
            : (int) $thread->customer_user_id;

        if (empty($receiverId)) {
            return response()->json([
                'message' => $message,
                'pushed' => false,
            ]);
        }

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'chat_message',
            'inbox_type' => 'message',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $receiverId,
            'data' => json_encode([
                'type' => 'chat_message',
                'thread_id' => $thread->id,
                'message_id' => $message->id,
                'sender_id' => $user->id,
                'shop_id' => $thread->shop_id,
                'order_id' => $thread->order_id,
                'title' => 'New message',
                'body' => Str::limit($payload['body'], 120),
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $title = 'New message';
        $body = \Illuminate\Support\Str::limit($payload['body'], 80);

        $data = [
            'type' => 'chat_message',
            'thread_id' => (string) $thread->id,
            'message_id' => (string) $message->id,
            'sender_id' => (string) $user->id,
        ];

        if (!empty($thread->shop_id)) $data['shop_id'] = (string) $thread->shop_id;
        if (!empty($thread->order_id)) $data['order_id'] = (string) $thread->order_id;

        /** @var \App\Services\PushNotificationService $push */
        $push = app(\App\Services\PushNotificationService::class);

        if (!empty($thread->shop_id) && (int)$receiverId === (int)$thread->vendor_user_id) {
            $push->sendToSpecificShop($receiverId, (int)$thread->shop_id, $title, $body, $data);
        } else {
            $push->sendToUser($receiverId, $title, $body, $data);
        }

        return response()->json([
            'message' => $message,
            'pushed' => true,
        ]);
    }
}
