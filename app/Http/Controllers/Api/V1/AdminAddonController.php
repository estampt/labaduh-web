<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminAddonController extends Controller
{
    public function index(Request $request)
    {
        $q = Addon::query()->latest();

        if (($active = $request->query('is_active')) !== null) {
            $q->where('is_active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($group = trim((string)$request->query('group_key', ''))) {
            $q->where('group_key', $group);
        }

        if ($search = trim((string)$request->query('search', ''))) {
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:120','unique:addons,name'],
            'group_key' => ['nullable','string','max:50'],
            'description' => ['nullable','string','max:255'],
            'price' => ['required','numeric','min:0'],
            'price_type' => ['required', Rule::in(['fixed','per_kg','per_item'])],
            'is_required' => ['sometimes','boolean'],
            'is_multi_select' => ['sometimes','boolean'],
            'sort_order' => ['sometimes','integer','min:0'],
            'is_active' => ['sometimes','boolean'],
        ]);

        $addon = Addon::create([
            'name' => $data['name'],
            'group_key' => $data['group_key'] ?? null,
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'price_type' => $data['price_type'],
            'is_required' => (bool)($data['is_required'] ?? false),
            'is_multi_select' => (bool)($data['is_multi_select'] ?? false),
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'is_active' => (bool)($data['is_active'] ?? true),
        ]);

        return response()->json(['data' => $addon], 201);
    }

    public function update(Request $request, Addon $addon)
    {
        $data = $request->validate([
            'name' => ['sometimes','string','max:120', Rule::unique('addons','name')->ignore($addon->id)],
            'group_key' => ['nullable','string','max:50'],
            'description' => ['nullable','string','max:255'],
            'price' => ['sometimes','numeric','min:0'],
            'price_type' => ['sometimes', Rule::in(['fixed','per_kg','per_item'])],
            'is_required' => ['sometimes','boolean'],
            'is_multi_select' => ['sometimes','boolean'],
            'sort_order' => ['sometimes','integer','min:0'],
            'is_active' => ['sometimes','boolean'],
        ]);

        $addon->fill($data)->save();

        return response()->json(['data' => $addon]);
    }

    public function destroy(Addon $addon)
    {
        $addon->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
