<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Order; // adjust namespace
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatMessageController extends Controller
{
    public function index(MessageThread $thread, Request $request)
    {
        $user = $request->user();

        $allowed = (int)$thread->customer_user_id === (int)$user->id
            || (!empty($thread->vendor_user_id) && (int)$thread->vendor_user_id === (int)$user->id);

        abort_unless($allowed, 403, 'Not allowed.');

        $messages = $thread->messages()
            ->orderBy('sent_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json(['messages' => $messages]);
    }

    public function store(MessageThread $thread, Request $request)
    {
        $user = $request->user();

        $allowed = (int)$thread->customer_user_id === (int)$user->id
            || (!empty($thread->vendor_user_id) && (int)$thread->vendor_user_id === (int)$user->id);

        abort_unless($allowed, 403, 'Not allowed.');

        // If order thread: enforce “no chat after completion/cancel”
        if ($thread->scope === 'order') {
            $order = Order::query()->findOrFail((int) $thread->order_id);
            $lockedStatuses = config('chat.order_locked_statuses', ['completed', 'cancelled']);

            if (in_array($order->status, $lockedStatuses, true)) {
                // lock thread if not locked
                if ($thread->locked_at === null) {
                    $thread->update(['locked_at' => now()]);
                }
                abort(409, 'Chat is closed because this order is already completed.');
            }
        }

        if ($thread->isLocked()) {
            abort(409, 'Chat is closed.');
        }

        $payload = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = Message::query()->create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'sender_id' => $user->id,
            'body' => $payload['body'],
            'sent_at' => now(),
        ]);

        $thread->update(['last_message_at' => now()]);

        // Determine receiver
        $receiverId = ((int)$user->id === (int)$thread->customer_user_id)
            ? (int) $thread->vendor_user_id
            : (int) $thread->customer_user_id;

        // If vendor_user_id not set yet (edge case), don’t push
        if (empty($receiverId)) {
            return response()->json(['message' => $message, 'pushed' => false]);
        }

        // Insert inbox notification row (your existing notifications table)
        // NOTE: your notifications.id is char(36) so UUID fits.
        \DB::table('notifications')->insert([
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
                'body' => \Illuminate\Support\Str::limit($payload['body'], 120),
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Send FCM push (uses your existing push service)
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

        /** @var \App\Services\PushService $push */
        $push = app(\App\Services\PushService::class);

        // If receiver is a vendor and thread has shop_id, target that shop
        if (!empty($thread->shop_id) && (int)$receiverId === (int)$thread->vendor_user_id) {
            $push->sendChatToSpecificShop($receiverId, (int)$thread->shop_id, $title, $body, $data);
        } else {
            $push->sendToUser($receiverId, $title, $body, $data);
        }

        return response()->json(['message' => $message, 'pushed' => true]);
    }
}
