<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JobRequest;
use App\Services\OrderBroadcastService;
use Illuminate\Http\Request;

class CustomerBroadcastController extends Controller
{
    public function broadcast(JobRequest $jobRequest, Request $r, OrderBroadcastService $svc)
    {
        $user = $r->user();
        if (!$user || $user->role !== 'customer') return response()->json(['message' => 'Forbidden.'], 403);
        if ($jobRequest->customer_id !== $user->id) return response()->json(['message' => 'Forbidden.'], 403);

        $data = $r->validate([
            'top_n' => ['nullable','integer','min:1','max:10'],
            'ttl_seconds' => ['nullable','integer','min:30','max:600'],
        ]);

        $offers = $svc->broadcast($jobRequest, $data['top_n'] ?? 5, $data['ttl_seconds'] ?? 90);
        return response()->json(['offers_created' => count($offers)]);
    }
}
