<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\AppSettings;
use Illuminate\Http\Request;

class AppSettingsController extends Controller
{
    public function index(Request $request)
    {
        $q = AppSetting::query()->orderBy('group')->orderBy('key');

        if ($group = $request->query('group')) {
            $q->where('group', $group);
        }

        return response()->json(['data' => $q->get()]);
    }

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

        return response()->json(['data' => $row]);
    }
}
