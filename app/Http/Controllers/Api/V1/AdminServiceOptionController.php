<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceOption;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminServiceOptionController extends Controller
{
    // GET /api/v1/admin/services/{service}/options?kind=addon|option|all&search=&is_active=
    public function index(Request $request, Service $service)
    {
        $kind = $request->query('kind', 'all'); // addon|option|all

        $q = ServiceOption::query()
            ->where('service_id', $service->id)
            ->orderBy('group_key')
            ->orderBy('sort_order')
            ->latest();

        if ($kind !== 'all') {
            $q->where('kind', $kind);
        }

        if (($active = $request->query('is_active')) !== null) {
            $q->where('is_active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")
                   ->orWhere('group_key', 'like', "%{$search}%")
                   ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'data' => $q->paginate($request->integer('per_page', 20)),
        ]);
    }

    // Convenience: GET /api/v1/admin/services/{service}/addons
    public function addons(Request $request, Service $service)
    {
        $request->merge(['kind' => 'addon']);
        return $this->index($request, $service);
    }

    // POST /api/v1/admin/services/{service}/options
    public function store(Request $request, Service $service)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['option','addon'])],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],

            // addon grouping / rules
            'group_key' => ['nullable', 'string', 'max:50'],
            'is_required' => ['sometimes', 'boolean'],
            'is_multi_select' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],

            // pricing
            'price' => ['required', 'numeric', 'min:0'],
            'price_type' => ['required', Rule::in(['fixed','per_kg','per_item'])], // matches your enum :contentReference[oaicite:1]{index=1}
        ]);

        $option = ServiceOption::create([
            'service_id' => $service->id,
            'kind' => $data['kind'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,

            'group_key' => $data['group_key'] ?? null,
            'is_required' => (bool)($data['is_required'] ?? false),
            'is_multi_select' => (bool)($data['is_multi_select'] ?? false),
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'is_active' => (bool)($data['is_active'] ?? true),

            'price' => $data['price'],
            'price_type' => $data['price_type'],
        ]);

        return response()->json(['data' => $option], 201);
    }

    // PATCH /api/v1/admin/service-options/{option}
    public function update(Request $request, ServiceOption $option)
    {
        $data = $request->validate([
            'kind' => ['sometimes', Rule::in(['option','addon'])],
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],

            'group_key' => ['nullable', 'string', 'max:50'],
            'is_required' => ['sometimes', 'boolean'],
            'is_multi_select' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],

            'price' => ['sometimes', 'numeric', 'min:0'],
            'price_type' => ['sometimes', Rule::in(['fixed','per_kg','per_item'])],
        ]);

        $option->fill($data);
        $option->save();

        return response()->json(['data' => $option]);
    }

    // DELETE /api/v1/admin/service-options/{option}
    public function destroy(ServiceOption $option)
    {
        $option->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
