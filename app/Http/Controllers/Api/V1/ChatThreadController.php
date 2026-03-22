<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MessageThread;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatThreadController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $threads = \App\Models\MessageThread::query()
            ->leftJoin('orders', 'orders.id', '=', 'message_threads.order_id')
            ->leftJoin('vendor_shops', function ($join) {
                $join->on('vendor_shops.id', '=', 'orders.accepted_shop_id')
                     ->orOn('vendor_shops.id', '=', 'message_threads.shop_id');
            })
            ->where(function ($q) use ($user) {
                $q->where('message_threads.customer_user_id', $user->id);

                if (!empty($user->vendor_id)) {
                    $q->orWhere('vendor_shops.vendor_id', $user->vendor_id);
                }
            })
            ->orderByDesc('message_threads.last_message_at')
            ->select([
                'message_threads.*',
                'vendor_shops.name as shop_name',
            ])
            ->get()
            ->map(function ($thread) {
                $lastMessage = $thread->messages()
                    ->latest('sent_at')
                    ->first();

                return [
                    'id' => (string) $thread->id,
                    'order_id' => $thread->order_id ? (string) $thread->order_id : null,
                    'shop_name' => $thread->shop_name ?: 'Shop',
                    'last_message_body' => $lastMessage?->body ?? 'No messages yet',
                    'last_message_time' => optional($thread->last_message_at)->format('H:i') ?? '',
                    'unread_count' => 0,
                ];
            })
            ->values();

        return response()->json([
            'threads' => $threads,
        ]);
    }

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
            abort(403, 'Order chat is disabled.');
        }

        if ($data['scope'] === 'shop' && !config('chat.shop_enabled')) {
            abort(403, 'Shop chat is disabled.');
        }

        if ($data['scope'] === 'order') {
            abort_unless(!empty($data['order_id']), 422, 'order_id is required for scope=order');

            Log::info('Looking up order chat thread', [
                'user_id' => $user?->id,
                'requested_order_id' => (int) $data['order_id'],
            ]);

            $order = Order::query()->findOrFail((int) $data['order_id']);

            Log::info('Order found for chat upsert', [
                'order_id' => $order->id,
                'accepted_shop_id' => $order->accepted_shop_id,
                'customer_id' => $order->customer_id,
                'accepted_vendor_id' => $order->accepted_vendor_id,
                'status' => $order->status,
                'user_id' => $user?->id,
                'user_vendor_id' => $user->vendor_id ?? null,
            ]);

            $isCustomer = (int) $order->customer_id === (int) $user->id;

            $isShopUser = false;
            if (!empty($order->accepted_shop_id)) {
                $isShopUser = DB::table('vendor_shops')
                    ->where('id', (int) $order->accepted_shop_id)
                    ->where('vendor_id', $user->vendor_id ?? 0)
                    ->exists();
            }

            Log::info('Order chat access check', [
                'user_id' => $user?->id,
                'user_vendor_id' => $user->vendor_id ?? null,
                'order_id' => $order->id,
                'accepted_shop_id' => $order->accepted_shop_id,
                'is_customer' => $isCustomer,
                'is_shop_user' => $isShopUser,
            ]);

            abort_unless($isCustomer || $isShopUser, 403, 'Not allowed.');

            $lockedStatuses = config('chat.order_locked_statuses', [
                'completed',
                'cancelled',
                'canceled',
            ]);

            $shouldLock = in_array($order->status, $lockedStatuses, true);

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

        $thread = MessageThread::query()->firstOrCreate(
            [
                'scope' => 'order',
                'shop_id' => $order->accepted_shop_id ?? null,
                'customer_user_id' => $order->customer_id,
                'order_id' => $order->id,
            ],
            [
                'id' => (string) Str::uuid(),
                'vendor_user_id' => $order->accepted_vendor_id ?? null,
                'locked_at' => $shouldLock ? now() : null,
                'last_message_at' => null,
            ]
        );

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
            $isShopUser = DB::table('vendor_shops')
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
