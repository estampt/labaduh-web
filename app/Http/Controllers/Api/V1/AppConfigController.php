<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AppSettings;

class AppConfigController extends Controller
{
    public function show(AppSettings $settings)
    {
        return response()->json([
            'data' => [
                'broadcast' => [
                    'min_radius_km' => $settings->get('broadcast.min_radius_km', 20.0),
                    'top_n' => $settings->get('broadcast.top_n', 100),
                    'ttl_seconds' => $settings->get('broadcast.ttl_seconds', 90),
                ],
            ],
        ]);
    }
}
