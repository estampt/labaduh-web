<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MessageThread;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatThreadController extends Controller
{

    public function upsert(Request $request)
{
    $user = $request->user();

    Log::info('Chat upsert started', [
        'user_id' => $user?->id,
        'payload' => $request->all(),
    ]);

    $data = $request->validate([
        'scope'    => ['required', 'string', 'in:order,shop'],
        'order_id' => ['nullable', 'integer'],
        'shop_id'  => ['nullable', 'integer'],
    ]);

    Log::info('Chat upsert validated data', [
        'user_id' => $user?->id,
        'data' => $data,
    ]);

    if ($data['scope'] === 'order' && !config('chat.order_enabled')) {
        Log::warning('Order chat is disabled', [
            'user_id' => $user?->id,
            'order_id' => $data['order_id'] ?? null,
        ]);

        abort(403, 'Order chat is disabled.');
    }

    if ($data['scope'] === 'shop' && !config('chat.shop_enabled')) {
        Log::warning('Shop chat is disabled', [
            'user_id' => $user?->id,
            'shop_id' => $data['shop_id'] ?? null,
        ]);

        abort(403, 'Shop chat is disabled.');
    }

    if ($data['scope'] === 'order') {
        abort_unless(!empty($data['order_id']), 422, 'order_id is required for scope=order');

        Log::info('Looking up order chat thread', [
            'user_id' => $user?->id,
            'order_id' => (int) $data['order_id'],
        ]);

        $order = Order::query()->findOrFail((int) $data['order_id']);

        Log::info('Order found for chat upsert', [
            'order_id' => $order->id,
            'accepted_shop_id' => $order->accepted_shop_id,
            'customer_id' => $order->customer_id,
            'accepted_vendor_id' => $order->accepted_vendor_id,
            'status' => $order->status,
        ]);

        $isCustomer = (int) $order->customer_id === (int) $user->id;
        $isVendor   = !empty($order->accepted_vendor_id)
            && (int) $order->accepted_vendor_id === (int) $user->id;

        Log::info('Order chat access check', [
            'user_id' => $user?->id,
            'order_id' => $order->id,
            'is_customer' => $isCustomer,
            'is_vendor' => $isVendor,
        ]);

        if (!($isCustomer || $isVendor)) {
            Log::warning('Order chat access denied', [
                'user_id' => $user?->id,
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'accepted_vendor_id' => $order->accepted_vendor_id,
            ]);
        }

        abort_unless($isCustomer || $isVendor, 403, 'Not allowed.');

        $lockedStatuses = config('chat.order_locked_statuses', [
            'completed',
            'cancelled',
            'canceled',
        ]);

        $shouldLock = in_array($order->status, $lockedStatuses, true);

        Log::info('Order chat lock evaluation', [
            'order_id' => $order->id,
            'order_status' => $order->status,
            'locked_statuses' => $lockedStatuses,
            'should_lock' => $shouldLock,
        ]);

        $thread = MessageThread::query()->firstOrCreate(
            [
                'scope' => 'order',
                'order_id' => $order->id,
            ],
            [
                'id' => (string) Str::uuid(),
                'shop_id' => $order->accepted_shop_id ?? null,
                'customer_user_id' => $order->customer_id,
                'vendor_user_id' => $order->accepted_vendor_id ?? null,
                'locked_at' => $shouldLock ? now() : null,
                'last_message_at' => null,
            ]
        );

        Log::info('Order thread fetched/created', [
            'thread_id' => $thread->id,
            'order_id' => $thread->order_id,
            'shop_id' => $thread->shop_id,
            'customer_user_id' => $thread->customer_user_id,
            'vendor_user_id' => $thread->vendor_user_id,
            'locked_at' => $thread->locked_at,
        ]);

        $dirty = false;
        $changes = [];

        if ((int) ($thread->shop_id ?? 0) !== (int) ($order->accepted_shop_id ?? 0)) {
            $changes['shop_id'] = [
                'from' => $thread->shop_id,
                'to' => $order->accepted_shop_id ?? null,
            ];
            $thread->shop_id = $order->accepted_shop_id ?? null;
            $dirty = true;
        }

        if ((int) $thread->customer_user_id !== (int) $order->customer_id) {
            $changes['customer_user_id'] = [
                'from' => $thread->customer_user_id,
                'to' => $order->customer_id,
            ];
            $thread->customer_user_id = $order->customer_id;
            $dirty = true;
        }

        if ((int) ($thread->vendor_user_id ?? 0) !== (int) ($order->accepted_vendor_id ?? 0)) {
            $changes['vendor_user_id'] = [
                'from' => $thread->vendor_user_id,
                'to' => $order->accepted_vendor_id ?? null,
            ];
            $thread->vendor_user_id = $order->accepted_vendor_id ?? null;
            $dirty = true;
        }

        if ($shouldLock && $thread->locked_at === null) {
            $changes['locked_at'] = [
                'from' => $thread->locked_at,
                'to' => now()->toDateTimeString(),
            ];
            $thread->locked_at = now();
            $dirty = true;
        }

        if ($dirty) {
            Log::info('Saving updated order thread', [
                'thread_id' => $thread->id,
                'changes' => $changes,
            ]);

            $thread->save();

            Log::info('Order thread saved successfully', [
                'thread_id' => $thread->id,
            ]);
        } else {
            Log::info('Order thread has no changes', [
                'thread_id' => $thread->id,
            ]);
        }

        Log::info('Chat upsert success for order scope', [
            'thread_id' => $thread->id,
            'order_id' => $order->id,
            'user_id' => $user?->id,
        ]);

        return response()->json([
            'thread' => $thread,
        ]);
    }

    abort_unless(!empty($data['shop_id']), 422, 'shop_id is required for scope=shop');

    Log::info('Looking up shop chat thread', [
        'user_id' => $user?->id,
        'shop_id' => (int) $data['shop_id'],
    ]);

    $thread = MessageThread::query()->firstOrCreate(
        [
            'scope' => 'shop',
            'shop_id' => (int) $data['shop_id'],
            'customer_user_id' => $user->id,
        ],
        [
            'id' => (string) Str::uuid(),
            'vendor_user_id' => null,
            'order_id' => null,
            'locked_at' => null,
            'last_message_at' => null,
        ]
    );

    Log::info('Shop thread fetched/created successfully', [
        'thread_id' => $thread->id,
        'shop_id' => $thread->shop_id,
        'customer_user_id' => $thread->customer_user_id,
        'vendor_user_id' => $thread->vendor_user_id,
    ]);

    Log::info('Chat upsert success for shop scope', [
        'thread_id' => $thread->id,
        'shop_id' => $thread->shop_id,
        'user_id' => $user?->id,
    ]);

    return response()->json([
        'thread' => $thread,
    ]);
}

    public function show(MessageThread $thread, Request $request)
    {
        $user = $request->user();

        $isCustomer = (int) $thread->customer_user_id === (int) $user->id;

        $isShopUser = false;
        if (!empty($thread->shop_id)) {
            $isShopUser = \DB::table('vendor_shops')
                ->where('id', $thread->shop_id)
                ->where('vendor_id', $user->vendor_id ?? 0)
                ->exists();
        }

        $allowed = $isCustomer || $isShopUser;
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

        return response()->json([
            'thread' => $thread,
        ]);
    }
}
