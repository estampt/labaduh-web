<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MessageThread;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatThreadController extends Controller
{
    public function upsert(Request $request)
    {
        $user = $request->user();

        \Log::info('ChatThread upsert called', [
            'user_id' => $user->id,
            'payload' => $request->all(),
        ]);

        $data = $request->validate([
            'scope'    => ['required', 'string', 'in:order,shop'],
            'order_id' => ['nullable', 'integer'],
            'shop_id'  => ['nullable', 'integer'],
        ]);

        \Log::info('ChatThread validated data', $data);

        // Feature toggles
        if ($data['scope'] === 'order' && !config('chat.order_enabled')) {
            \Log::warning('Order chat disabled');
            abort(403, 'Order chat is disabled.');
        }

        if ($data['scope'] === 'shop' && !config('chat.shop_enabled')) {
            \Log::warning('Shop chat disabled');
            abort(403, 'Shop chat is disabled.');
        }

        /*
        |--------------------------------------------------------------------------
        | ORDER SCOPE
        |--------------------------------------------------------------------------
        */
        if ($data['scope'] === 'order') {

            abort_unless(!empty($data['order_id']), 422, 'order_id is required for scope=order');

            \Log::info('Loading order', [
                'order_id' => $data['order_id']
            ]);

            $order = Order::query()->findOrFail((int) $data['order_id']);

            \Log::info('Order loaded', [
                'order_id' => $order->id,
                'status' => $order->status,
                'customer_user_id' => $order->customer_user_id,
                'shop_id' => $order->shop_id,
            ]);

            // Access check
            $isCustomer = (int)$order->customer_user_id === (int)$user->id;
            $isVendor   = !empty($order->vendor_user_id)
                && (int)$order->vendor_user_id === (int)$user->id;

            \Log::info('Access check', [
                'isCustomer' => $isCustomer,
                'isVendor' => $isVendor,
            ]);

            abort_unless($isCustomer || $isVendor, 403, 'Not allowed.');

            // Lock decision
            $lockedStatuses = config('chat.order_locked_statuses', ['completed', 'cancelled']);
            $shouldLock = in_array($order->status, $lockedStatuses, true);

            \Log::info('Lock evaluation', [
                'order_status' => $order->status,
                'shouldLock' => $shouldLock,
            ]);

            // Create or get thread
            $thread = MessageThread::query()->firstOrCreate(
                [
                    'scope' => 'order',
                    'order_id' => $order->id
                ],
                [
                    'id' => (string) Str::uuid(),
                    'shop_id' => $order->shop_id ?? null,
                    'customer_user_id' => $order->customer_user_id,
                    'vendor_user_id' => $order->vendor_user_id ?? null,
                    'locked_at' => $shouldLock ? now() : null,
                ]
            );

            \Log::info('Thread upserted', [
                'thread_id' => $thread->id,
                'shop_id' => $thread->shop_id,
                'locked_at' => $thread->locked_at,
            ]);

            // Ensure lock if status changed later
            if ($shouldLock && $thread->locked_at === null) {
                $thread->update(['locked_at' => now()]);

                \Log::warning('Thread locked after creation', [
                    'thread_id' => $thread->id
                ]);
            }

            return response()->json(['thread' => $thread]);
        }

        /*
        |--------------------------------------------------------------------------
        | SHOP SCOPE
        |--------------------------------------------------------------------------
        */
        abort_unless(!empty($data['shop_id']), 422, 'shop_id is required for scope=shop');

        \Log::info('Shop thread upsert', [
            'shop_id' => $data['shop_id'],
            'customer_user_id' => $user->id,
        ]);

        $thread = MessageThread::query()->firstOrCreate(
            [
                'scope' => 'shop',
                'shop_id' => (int)$data['shop_id'],
                'customer_user_id' => $user->id
            ],
            [
                'id' => (string) Str::uuid(),
                'vendor_user_id' => null,
                'order_id' => null,
                'locked_at' => null,
            ]
        );

        \Log::info('Shop thread created/fetched', [
            'thread_id' => $thread->id
        ]);

        return response()->json(['thread' => $thread]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW THREAD
    |--------------------------------------------------------------------------
    */
    public function show(MessageThread $thread, Request $request)
    {
        $user = $request->user();

        \Log::info('Thread show called', [
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);

        // Customer access
        $isCustomer = (int) $thread->customer_user_id === (int) $user->id;

        // Shop access
        $isShopUser = false;

        if (!empty($thread->shop_id)) {
            $isShopUser = \DB::table('vendor_shops')
                ->where('id', $thread->shop_id)
                ->where('vendor_id', $user->vendor_id ?? 0)
                ->exists();
        }

        \Log::info('Thread access evaluation', [
            'isCustomer' => $isCustomer,
            'isShopUser' => $isShopUser,
        ]);

        $allowed = $isCustomer || $isShopUser;

        if (!$allowed) {
            \Log::warning('Thread access denied', [
                'thread_id' => $thread->id,
                'user_id' => $user->id,
            ]);
        }

        abort_unless($allowed, 403, 'Not allowed.');

        return response()->json([
            'thread' => $thread
        ]);
    }
}
