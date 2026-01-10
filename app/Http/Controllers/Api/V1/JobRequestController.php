<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JobRequest;
use App\Models\JobRequestItem;
use App\Services\VendorMatchingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobRequestController extends Controller
{
    public function createAndMatch(Request $r, VendorMatchingService $matching)
    {
        $user = $r->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        if ($user->role !== 'customer') return response()->json(['message' => 'Only customers can create requests.'], 403);

        $data = $r->validate([
            'pickup_lat' => ['required','numeric'],
            'pickup_lng' => ['required','numeric'],
            'dropoff_lat' => ['required','numeric'],
            'dropoff_lng' => ['required','numeric'],

            'pickup_date' => ['required','date'],
            'pickup_time_start' => ['required','date_format:H:i'],
            'pickup_time_end' => ['required','date_format:H:i'],

            'delivery_date' => ['required','date'],
            'delivery_time_start' => ['required','date_format:H:i'],
            'delivery_time_end' => ['required','date_format:H:i'],

            'items' => ['required','array','min:1'],
            'items.*.service_id' => ['required','integer'],
            'items.*.category_code' => ['nullable','string','max:50'],
            'items.*.category_label' => ['nullable','string','max:100'],
            'items.*.bag_count' => ['nullable','integer','min:1','max:999'],
            'items.*.weight_kg' => ['required','numeric','min:0'],
            'items.*.options' => ['nullable','array'],

            'notes' => ['nullable','string','max:2000'],
        ]);

        $estimatedKg = 0.0;
        $minKg = (float) config('pricing.min_kg_per_line', 6.0);
        $ratePerKg = (float) config('pricing.rate_per_kg', 8.0);

        $lineSnapshots = [];
        foreach ($data['items'] as $it) {
            $kg = (float)$it['weight_kg'];
            $estimatedKg += $kg;
            $billedKg = max($minKg, $kg);
            $lineSnapshots[] = [
                'service_id' => (int)$it['service_id'],
                'category_code' => $it['category_code'] ?? null,
                'category_label' => $it['category_label'] ?? null,
                'bag_count' => $it['bag_count'] ?? null,
                'entered_kg' => $kg,
                'billed_kg' => $billedKg,
                'line_subtotal' => round($billedKg * $ratePerKg, 2),
                'options' => $it['options'] ?? null,
            ];
        }

        return DB::transaction(function () use ($data, $user, $matching, $estimatedKg) {
            $jr = JobRequest::create([
                'customer_id' => $user->id,
                'pickup_lat' => $data['pickup_lat'],
                'pickup_lng' => $data['pickup_lng'],
                'dropoff_lat' => $data['dropoff_lat'],
                'dropoff_lng' => $data['dropoff_lng'],
                'pickup_date' => $data['pickup_date'],
                'pickup_time_start' => $data['pickup_time_start'],
                'pickup_time_end' => $data['pickup_time_end'],
                'delivery_date' => $data['delivery_date'],
                'delivery_time_start' => $data['delivery_time_start'],
                'delivery_time_end' => $data['delivery_time_end'],
                'estimated_kg' => $estimatedKg,
                'assignment_status' => 'draft',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $it) {
                $kg = (float)$it['weight_kg'];
                $billedKg = max($minKg, $kg);
                JobRequestItem::create([
                    'job_request_id' => $jr->id,
                    'service_id' => $it['service_id'],
                    'category_code' => $it['category_code'] ?? null,
                    'category_label' => $it['category_label'] ?? null,
                    'bag_count' => $it['bag_count'] ?? null,
                    'weight_kg' => $kg,
                    'min_kg_applied' => $billedKg,
                    'options' => $it['options'] ?? null,
                    'price_snapshot' => [
                        'entered_kg' => $kg,
                        'billed_kg' => $billedKg,
                        'line_subtotal' => round($billedKg * $ratePerKg, 2),
                    ],
                ]);
            }

            $matches = $matching->match([
                'pickup_lat' => $jr->pickup_lat,
                'pickup_lng' => $jr->pickup_lng,
                'pickup_date' => $jr->pickup_date->format('Y-m-d'),
                'pickup_time_start' => $jr->pickup_time_start,
                'pickup_time_end' => $jr->pickup_time_end,
                'delivery_date' => $jr->delivery_date->format('Y-m-d'),
                'delivery_time_start' => $jr->delivery_time_start,
                'delivery_time_end' => $jr->delivery_time_end,
                'estimated_kg' => (float)$jr->estimated_kg,
                'items' => $lineSnapshots,
            ], 10);

            $jr->update(['match_snapshot' => $matches]);

            return response()->json([
                'job_request' => $jr->fresh()->load('items'),
                'matches' => $matches,
            ], 201);
        });
    }
}
