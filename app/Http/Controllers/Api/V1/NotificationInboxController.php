<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationInboxController extends Controller
{
    /**
     * GET /api/v1/notifications?type=ops|chat
     * If no type is provided, returns all.
     *
     * Note: Because notifications.data is TEXT, we decode + filter in PHP.
     * For large scale, we can later optimize by storing category in notifications.type.
     */
    public function index(Request $request)
    {
        $perPage = max(1, (int) $request->query('per_page', 20));
        $page = max(1, (int) $request->query('page', 1));
        $type = $request->query('type'); // ops | chat | null

        // Basic validation (optional)
        if ($type !== null && !in_array($type, ['ops', 'chat'], true)) {
            return response()->json([
                'message' => 'Invalid type. Allowed: ops, chat',
            ], 422);
        }

        // Pull a larger slice so filtering still returns items.
        // (Simple + reliable for now; can be optimized later.)
        $fetch = $perPage * 5;

        $raw = $request->user()
            ->notifications()
            ->orderByDesc('created_at')
            ->paginate($fetch, ['*'], 'page', $page);

        $decoded = $raw->getCollection()->map(function ($n) {
            $n->data = is_string($n->data) ? (json_decode($n->data, true) ?? []) : ($n->data ?? []);
            return $n;
        });

        if ($type !== null) {
            $decoded = $decoded->filter(function ($n) use ($type) {
                return ($n->data['type'] ?? null) === $type;
            })->values();
        }

        // Now re-page the filtered results (per_page items)
        $slice = $decoded->slice(0, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $slice,
            // total is unknown without scanning all rows; return "best effort" total
            // We set it to current page size + prior pages approximation
            // so clients can still paginate forward safely.
            (($page - 1) * $perPage) + $decoded->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return response()->json($paginator);
    }

    /**
     * GET /api/v1/notifications/unread-count?type=ops|chat
     * If no type is provided, returns unread count for all.
     */
    public function unreadCount(Request $request)
    {
        $type = $request->query('type'); // ops | chat | null

        if ($type !== null && !in_array($type, ['ops', 'chat'], true)) {
            return response()->json([
                'message' => 'Invalid type. Allowed: ops, chat',
            ], 422);
        }

        // If no type: fast DB count
        if ($type === null) {
            return response()->json([
                'unread' => $request->user()->unreadNotifications()->count(),
            ]);
        }

        // Typed unread count: decode + filter
        $unread = $request->user()->unreadNotifications()->get();

        $count = $unread->map(function ($n) {
                $data = is_string($n->data) ? (json_decode($n->data, true) ?? []) : ($n->data ?? []);
                return $data;
            })
            ->filter(fn ($data) => ($data['type'] ?? null) === $type)
            ->count();

        return response()->json(['unread' => $count]);
    }

    /**
     * POST /api/v1/notifications/{id}/read
     */
    public function markRead(Request $request, string $id)
    {
        $notif = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notif->markAsRead();

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/v1/notifications/read-all
     * Optional: supports ?type=ops|chat (if you want per-icon "mark all")
     */
    public function markAllRead(Request $request)
    {
        $type = $request->query('type'); // ops|chat|null

        if ($type !== null && !in_array($type, ['ops', 'chat'], true)) {
            return response()->json([
                'message' => 'Invalid type. Allowed: ops, chat',
            ], 422);
        }

        // No type: fast
        if ($type === null) {
            $request->user()->unreadNotifications->markAsRead();
            return response()->json(['ok' => true]);
        }

        // Typed mark-all-read
        $unread = $request->user()->unreadNotifications()->get();

        foreach ($unread as $n) {
            $data = is_string($n->data) ? (json_decode($n->data, true) ?? []) : ($n->data ?? []);
            if (($data['type'] ?? null) === $type) {
                $n->markAsRead();
            }
        }

        return response()->json(['ok' => true]);
    }
}
