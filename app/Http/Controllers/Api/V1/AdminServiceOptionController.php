<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ServiceOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminServiceOptionController extends Controller
{
    public function index(Request $request)
    {
        $q = ServiceOption::query()
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($request->filled('kind')) {
            $q->where('kind', $request->string('kind'));
        }

        if ($request->filled('active')) {
            $q->where('is_active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json(['data' => $q->paginate(20)]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules(isUpdate: false));
        $data = $this->sanitizeDefaultSelected($validated);

        $row = ServiceOption::create($data);

        return response()->json(['data' => $row], 201);
    }

    public function show(ServiceOption $serviceOption)
    {
        return response()->json(['data' => $serviceOption]);
    }

    public function update(Request $request, ServiceOption $serviceOption)
    {
        $validated = $request->validate($this->rules(isUpdate: true));
        $data = $this->sanitizeDefaultSelected($validated);

        $serviceOption->fill($data)->save();

        return response()->json(['data' => $serviceOption]);
    }

    public function destroy(ServiceOption $serviceOption)
    {
        $serviceOption->delete();
        return response()->json(['ok' => true]);
    }

    public function toggleActive(ServiceOption $serviceOption)
    {
        $serviceOption->is_active = !$serviceOption->is_active;
        $serviceOption->save();

        return response()->json(['data' => $serviceOption]);
    }

    private function rules(bool $isUpdate): array
    {
        // Use "sometimes" on update so partial PATCH/PUT payloads work.
        $req = $isUpdate ? 'sometimes' : 'required';

        return [
            'name' => [$req, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],

            // master fields
            'kind' => [$req, 'in:option,addon'],
            'group_key' => ['nullable', 'string', 'max:100'],

            'price' => [$req, 'numeric', 'min:0'],
            'price_type' => [$req, 'in:fixed,per_kg,per_item'],

            'is_required' => ['sometimes', 'boolean'],
            'is_multi_select' => ['sometimes', 'boolean'],

            // optional column depending on migration state
            'is_default_selected' => ['sometimes', 'boolean'],

            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function sanitizeDefaultSelected(array $data): array
    {
        if (!Schema::hasColumn('service_options', 'is_default_selected')) {
            unset($data['is_default_selected']);
        }
        return $data;
    }
}
