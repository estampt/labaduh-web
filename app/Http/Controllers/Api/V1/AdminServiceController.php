<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminServiceController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Service::orderBy('name')->get()
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request, creating: true);
        $service = Service::create($data);
        return response()->json(['data' => $service], 201);
    }

    public function update(Request $request, Service $service)
    {
        $data = $this->validatePayload($request, creating: false);
        $service->update($data);
        return response()->json(['data' => $service]);
    }

    public function destroy(Service $service)
    {
        $service->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function setActive(Request $request, Service $service)
    {
        $data = $request->validate([
            'is_active' => ['required','boolean'],
        ]);

        $service->is_active = $data['is_active'];
        $service->save();

        return response()->json(['data' => $service]);
    }

    private function validatePayload(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'base_unit' => [$creating ? 'required' : 'sometimes', Rule::in(['kg','item','order'])],
            'is_active' => ['sometimes','boolean'],

            'default_pricing_model' => ['sometimes', Rule::in(['per_kg_min','per_piece'])],
            'default_min_kg' => ['nullable','numeric','min:0'],
            'default_rate_per_kg' => ['nullable','numeric','min:0'],
            'default_rate_per_piece' => ['nullable','numeric','min:0'],

            'allow_vendor_override_price' => ['sometimes','boolean'],
        ]);
    }
}
