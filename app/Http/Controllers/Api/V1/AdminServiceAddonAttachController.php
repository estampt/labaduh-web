<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\Service;
use Illuminate\Http\Request;

class AdminServiceAddonAttachController extends Controller
{
    public function index(Service $service)
    {
        // returns all attached addons with pivot fields
        $addons = $service->addons()
            ->orderByPivot('sort_order')
            ->get()
            ->map(function ($a) {
                return [
                    'id' => $a->id,
                    'name' => $a->name,
                    'group_key' => $a->group_key,
                    'price' => $a->price,
                    'price_type' => $a->price_type,
                    'is_active' => $a->is_active,
                    'pivot' => [
                        'is_active' => (bool) $a->pivot->is_active,
                        'sort_order' => (int) $a->pivot->sort_order,
                    ],
                ];
            });

        return response()->json(['data' => $addons]);
    }

    public function attach(Request $request, Service $service)
    {
        $data = $request->validate([
            'addon_id' => ['required','integer','exists:addons,id'],
        ]);

        $service->addons()->syncWithoutDetaching([
            $data['addon_id'] => ['is_active' => true, 'sort_order' => 0],
        ]);

        return response()->json(['message' => 'Attached']);
    }

    public function detach(Request $request, Service $service)
    {
        $data = $request->validate([
            'addon_id' => ['required','integer','exists:addons,id'],
        ]);

        $service->addons()->detach($data['addon_id']);

        return response()->json(['message' => 'Detached']);
    }

    public function updatePivot(Request $request, Service $service, Addon $addon)
    {
        $data = $request->validate([
            'is_active' => ['sometimes','boolean'],
            'sort_order' => ['sometimes','integer','min:0'],
        ]);

        $service->addons()->updateExistingPivot($addon->id, $data);

        return response()->json(['message' => 'Updated']);
    }
}
