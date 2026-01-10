<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use Illuminate\Http\Request;

class DriverDeliveryController extends Controller
{
    public function myDeliveries(Request $r)
    {
        $user = $r->user();
        if (!$user || $user->role !== 'driver') return response()->json(['message' => 'Forbidden.'], 403);

        // In this scaffold, driver_id is not auto-linked from user. You can link via drivers.user_id.
        $driver = \App\Models\Driver::where('user_id', $user->id)->first();
        if (!$driver) return response()->json(['message' => 'Driver profile not found.'], 404);

        return Delivery::where('driver_id', $driver->id)->orderByDesc('id')->paginate(20);
    }

    public function updateStatus(Delivery $delivery, Request $r)
    {
        $user = $r->user();
        if (!$user || $user->role !== 'driver') return response()->json(['message' => 'Forbidden.'], 403);

        $driver = \App\Models\Driver::where('user_id', $user->id)->first();
        if (!$driver || $delivery->driver_id !== $driver->id) return response()->json(['message' => 'Forbidden.'], 403);

        $data = $r->validate([
            'status' => ['required','in:accepted,arrived,in_transit,completed,cancelled'],
            'notes' => ['nullable','string','max:255'],
        ]);

        $delivery->update(['status' => $data['status']]);

        \App\Models\DeliveryEvent::create([
            'delivery_id' => $delivery->id,
            'status' => $data['status'],
            'created_by' => $user->id,
            'notes' => $data['notes'] ?? null,
        ]);

        return $delivery->fresh('events');
    }
}
