<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\AppSettings;
use Illuminate\Http\Request;

class AppSettingsController extends Controller
{
    /**
     * ---------------------------------------------------------
     * GET /api/v1/app-settings
     * List settings (raw DB rows)
     * Optional: ?group=broadcast
     * ---------------------------------------------------------
     */
    public function index(Request $request)
    {
        $q = AppSetting::query()
            ->orderBy('group')
            ->orderBy('key');

        if ($group = $request->query('group')) {
            $q->where('group', $group);
        }

        return response()->json([
            'data' => $q->get()
        ]);
    }

    /**
     * ---------------------------------------------------------
     * GET /api/v1/app-settings/get
     * Retrieve resolved setting value
     *
     * Examples:
     * ?key=broadcast.min_radius_km
     * ?key=fees.delivery_fee
     * ---------------------------------------------------------
     */
    public function get(Request $request, AppSettings $settings)
    {
        $data = $request->validate([
            'key' => ['required','string','max:190'],
            'default' => ['nullable'],
        ]);

        $value = $settings->get(
            $data['key'],
            $data['default'] ?? null
        );

        return response()->json([
            'key' => $data['key'],
            'value' => $value,
        ]);
    }

    /**
     * ---------------------------------------------------------
     * GET /api/v1/app-settings/group/{group}
     * Retrieve all settings in a group (resolved values)
     * ---------------------------------------------------------
     */
    public function group(string $group, AppSettings $settings)
    {
        $rows = AppSetting::where('group', $group)->get();

        $resolved = [];

        foreach ($rows as $row) {
            $resolved[$row->key] = $settings->castValue(
                $row->value,
                $row->type
            );
        }

        return response()->json([
            'group' => $group,
            'data' => $resolved,
        ]);
    }

    /**
     * ---------------------------------------------------------
     * POST /api/v1/app-settings/upsert
     * Create or update a setting
     * ---------------------------------------------------------
     */
    public function upsert(Request $request, AppSettings $settings)
    {
        $data = $request->validate([
            'key' => ['required','string','max:190'],
            'value' => ['nullable'],
            'type' => ['required','in:string,int,float,bool,json'],
            'group' => ['nullable','string','max:50'],
        ]);

        $row = $settings->set(
            $data['key'],
            $data['value'],
            $data['type'],
            $data['group'] ?? null,
            $request->user()?->id
        );

        return response()->json([
            'data' => $row
        ]);
    }
}
