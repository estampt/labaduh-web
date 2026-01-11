<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Settings', [
            'flags' => [
                'walk_in_enabled' => (bool) config('fulfillment.walk_in.enabled', true),
                'third_party_enabled' => (bool) config('fulfillment.third_party.enabled', true),
                'inhouse_enabled' => (bool) config('fulfillment.inhouse.enabled', true),
            ],
        ]);
    }
}
