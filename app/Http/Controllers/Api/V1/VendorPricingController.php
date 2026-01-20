<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\VendorService;
use App\Models\VendorShop;
use App\Models\VendorShopServicePrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VendorPricingController extends Controller
{
    /**
     * GET /api/v1/vendor/pricing
     * Returns:
     * - system services catalog (active)
     * - vendor services config (enabled/default/override)
     * - vendor shops
     * - effective shop pricing rows for today (non-overlapping so max 1 per shop+service)
     */
    public function index(Request $request)
    {
        $vendorId = $this->getVendorIdOrFail($request);

        $services = Service::query()
            ->orderBy('name')
            ->get();

        $vendorServices = VendorService::query()
            ->with('service')
            ->where('vendor_id', $vendorId)
            ->orderBy('service_id')
            ->get()
            ->keyBy('service_id');

        $shops = VendorShop::query()
            ->where('vendor_id', $vendorId)
            ->orderBy('id')
            ->get();

        $today = now()->toDateString();

        $shopPriceRows = VendorShopServicePrice::query()
            ->whereIn('shop_id', $shops->pluck('id'))
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today);
            })
            ->orderBy('shop_id')
            ->orderBy('service_id')
            ->get()
            ->groupBy('shop_id');

        return response()->json([
            'vendor_id' => $vendorId,
            'services' => $services,
            'vendor_services' => $vendorServices,
            'shops' => $shops,
            'shop_effective_prices' => $shopPriceRows,
            'effective_date' => $today,
        ]);
    }

    /**
     * POST /api/v1/vendor/pricing/service-prices
     *
     * Payload (vendor scope):
     * {
     *   "scope": "vendor",
     *   "items": [
     *     {
     *       "service_id": 1,
     *       "is_enabled": true,
     *       "use_default_pricing": false,
     *       "pricing_model": "per_kg_min",
     *       "min_kg": 3,
     *       "rate_per_kg": 45
     *     }
     *   ]
     * }
     *
     * Payload (shop scope):
     * {
     *   "scope": "shop",
     *   "shop_id": 10,
     *   "items": [
     *     {
     *       "service_id": 1,
     *       "is_enabled": true,
     *       "use_vendor_default_pricing": false,
     *       "pricing_model": "per_kg_min",
     *       "min_kg": 5,
     *       "rate_per_kg": 40,
     *       "effective_from": "2026-01-20",
     *       "effective_to": "2026-02-20"
     *     }
     *   ]
     * }
     */
    public function upsertServicePrices(Request $request)
    {
        $vendorId = $this->getVendorIdOrFail($request);

        $data = $request->validate([
            'scope' => ['required', Rule::in(['vendor', 'shop'])],
            'shop_id' => ['required_if:scope,shop', 'integer'],
            'items' => ['required', 'array', 'min:1'],

            'items.*.service_id' => ['required', 'integer', 'exists:services,id'],

            // enable flags
            'items.*.is_enabled' => ['nullable', 'boolean'],

            // vendor scope flags
            'items.*.use_default_pricing' => ['nullable', 'boolean'],

            // shop scope flags
            'items.*.use_vendor_default_pricing' => ['nullable', 'boolean'],

            // pricing model
            'items.*.pricing_model' => ['nullable', Rule::in(['per_kg_min', 'per_piece'])],

            // kg fields
            'items.*.min_kg' => ['nullable', 'numeric', 'min:0'],
            'items.*.rate_per_kg' => ['nullable', 'numeric', 'min:0'],

            // piece fields
            'items.*.rate_per_piece' => ['nullable', 'numeric', 'min:0'],

            // effectivity (shop scope)
            'items.*.effective_from' => ['nullable', 'date'],
            'items.*.effective_to' => ['nullable', 'date'],
        ]);

        if ($data['scope'] === 'shop') {
            $shop = VendorShop::query()
                ->where('id', $data['shop_id'])
                ->where('vendor_id', $vendorId)
                ->first();

            if (!$shop) {
                return response()->json(['message' => 'Shop not found or not owned by vendor.'], 403);
            }
        }

        DB::transaction(function () use ($vendorId, $data) {
            foreach ($data['items'] as $item) {
                $service = Service::query()->findOrFail($item['service_id']);

                if ($data['scope'] === 'vendor') {
                    $this->saveVendorService($vendorId, $service, $item);
                } else {
                    $this->saveShopServicePrice((int)$data['shop_id'], $vendorId, $service, $item);
                }
            }
        });

        return response()->json(['message' => 'Pricing saved.']);
    }

    /**
     * DELETE /api/v1/vendor/pricing/shop-price/{id}
     * Deletes one shop pricing row (e.g. a future effective range)
     */
    public function deleteShopPrice(Request $request, int $id)
    {
        $vendorId = $this->getVendorIdOrFail($request);

        $row = VendorShopServicePrice::query()->findOrFail($id);

        $shop = VendorShop::query()
            ->where('id', $row->shop_id)
            ->where('vendor_id', $vendorId)
            ->first();

        if (!$shop) {
            return response()->json(['message' => 'Not allowed.'], 403);
        }

        $row->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    // -------------------------
    // Internals
    // -------------------------

    private function getVendorIdOrFail(Request $request): int
    {
        $user = $request->user();

        $vendorId = (int)($user->vendor_id ?? 0);
        if ($vendorId > 0) return $vendorId;

        // fallback to relation if vendor_id is not filled for some reason
        $relId = (int)optional($user->vendor)->id;
        if ($relId > 0) return $relId;

        abort(response()->json(['message' => 'Vendor not found for user.'], 403));
    }

    private function saveVendorService(int $vendorId, Service $service, array $item): void
    {
        $vs = VendorService::firstOrNew([
            'vendor_id' => $vendorId,
            'service_id' => $service->id,
        ]);

        // enable/disable
        if (array_key_exists('is_enabled', $item)) {
            $vs->is_enabled = (bool)$item['is_enabled'];
        } elseif ($vs->exists === false) {
            $vs->is_enabled = true;
        }

        $useDefault = array_key_exists('use_default_pricing', $item)
            ? (bool)$item['use_default_pricing']
            : ($vs->use_default_pricing ?? true);

        $vs->use_default_pricing = $useDefault;

        if ($useDefault) {
            // clean overrides
            $vs->pricing_model = null;
            $vs->min_kg = null;
            $vs->rate_per_kg = null;
            $vs->rate_per_piece = null;
            $vs->save();
            return;
        }

        // override pricing
        $model = $item['pricing_model'] ?? $service->default_pricing_model ?? 'per_kg_min';
        $vs->pricing_model = $model;

        if ($model === 'per_kg_min') {
            // require rate_per_kg if overriding
            if (!array_key_exists('rate_per_kg', $item) || $item['rate_per_kg'] === null) {
                throw ValidationException::withMessages([
                    'items' => ['rate_per_kg is required when overriding per_kg_min pricing.'],
                ]);
            }
            $vs->min_kg = $item['min_kg'] ?? null;
            $vs->rate_per_kg = $item['rate_per_kg'];
            $vs->rate_per_piece = null;
        } else { // per_piece
            if (!array_key_exists('rate_per_piece', $item) || $item['rate_per_piece'] === null) {
                throw ValidationException::withMessages([
                    'items' => ['rate_per_piece is required when overriding per_piece pricing.'],
                ]);
            }
            $vs->rate_per_piece = $item['rate_per_piece'];
            $vs->min_kg = null;
            $vs->rate_per_kg = null;
        }

        $vs->save();
    }

    private function saveShopServicePrice(int $shopId, int $vendorId, Service $service, array $item): void
    {
        // Safety: ensure shop belongs to vendor (extra guard)
        $shopOk = VendorShop::query()
            ->where('id', $shopId)
            ->where('vendor_id', $vendorId)
            ->exists();
        if (!$shopOk) {
            throw ValidationException::withMessages(['shop_id' => ['Shop not found or not owned by vendor.']]);
        }

        $isEnabled = array_key_exists('is_enabled', $item) ? (bool)$item['is_enabled'] : true;

        $useVendorDefault = array_key_exists('use_vendor_default_pricing', $item)
            ? (bool)$item['use_vendor_default_pricing']
            : true;

        $effectiveFrom = $item['effective_from'] ?? null;
        $effectiveTo = $item['effective_to'] ?? null;

        // if both provided, ensure from <= to
        if ($effectiveFrom !== null && $effectiveTo !== null) {
            if (strtotime($effectiveFrom) > strtotime($effectiveTo)) {
                throw ValidationException::withMessages([
                    'items' => ['effective_to must be after or equal to effective_from.'],
                ]);
            }
        }

        // If overriding pricing, validate fields and reject overlap
        $pricingModel = null;
        $minKg = null;
        $rateKg = null;
        $ratePiece = null;

        if (!$useVendorDefault) {
            $pricingModel = $item['pricing_model'] ?? $service->default_pricing_model ?? 'per_kg_min';

            if ($pricingModel === 'per_kg_min') {
                if (!array_key_exists('rate_per_kg', $item) || $item['rate_per_kg'] === null) {
                    throw ValidationException::withMessages([
                        'items' => ['rate_per_kg is required when overriding per_kg_min pricing.'],
                    ]);
                }
                $minKg = $item['min_kg'] ?? null;
                $rateKg = $item['rate_per_kg'];
            } else { // per_piece
                if (!array_key_exists('rate_per_piece', $item) || $item['rate_per_piece'] === null) {
                    throw ValidationException::withMessages([
                        'items' => ['rate_per_piece is required when overriding per_piece pricing.'],
                    ]);
                }
                $ratePiece = $item['rate_per_piece'];
            }

            // ✅ reject overlap (per shop+service)
            $this->assertNoOverlap($shopId, $service->id, $effectiveFrom, $effectiveTo, null);
        }

        // Multiple rows allowed because of effectivity ranges.
        // We'll "upsert" by exact same range bounds.
        $row = VendorShopServicePrice::firstOrNew([
            'shop_id' => $shopId,
            'service_id' => $service->id,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
        ]);

        // If updating an existing row (same range), allow it (ignore itself in overlap check).
        if ($row->exists && !$useVendorDefault) {
            $this->assertNoOverlap($shopId, $service->id, $effectiveFrom, $effectiveTo, $row->id);
        }

        $row->is_enabled = $isEnabled;
        $row->use_vendor_default_pricing = $useVendorDefault;

        if ($useVendorDefault) {
            // store no override fields
            $row->pricing_model = null;
            $row->min_kg = null;
            $row->rate_per_kg = null;
            $row->rate_per_piece = null;
        } else {
            $row->pricing_model = $pricingModel;
            $row->min_kg = $minKg;
            $row->rate_per_kg = $rateKg;
            $row->rate_per_piece = $ratePiece;
        }

        $row->save();
    }

    /**
     * Reject any overlapping range for same shop_id + service_id.
     * Supports NULL (open-ended) bounds.
     *
     * Overlap condition:
     * existing_from <= new_to  AND  existing_to >= new_from
     * with NULL meaning -infinity/+infinity.
     */
    private function assertNoOverlap(
        int $shopId,
        int $serviceId,
        ?string $newFrom,
        ?string $newTo,
        ?int $ignoreId
    ): void {
        $q = VendorShopServicePrice::query()
            ->where('shop_id', $shopId)
            ->where('service_id', $serviceId);

        if ($ignoreId !== null) {
            $q->where('id', '!=', $ignoreId);
        }

        // existing_from <= new_to  (or new_to is NULL -> always true)
        $q->where(function ($qq) use ($newTo) {
            if ($newTo === null) {
                // new range has no end => overlaps any existing that starts anytime
                $qq->whereNotNull('effective_from')->orWhereNull('effective_from');
            } else {
                $qq->whereNull('effective_from')->orWhere('effective_from', '<=', $newTo);
            }
        });

        // existing_to >= new_from (or new_from is NULL -> always true)
        $q->where(function ($qq) use ($newFrom) {
            if ($newFrom === null) {
                // new range has no start => overlaps any existing that ends anytime
                $qq->whereNotNull('effective_to')->orWhereNull('effective_to');
            } else {
                $qq->whereNull('effective_to')->orWhere('effective_to', '>=', $newFrom);
            }
        });

        if ($q->exists()) {
            throw ValidationException::withMessages([
                'items' => ['Effectivity date range overlaps an existing shop pricing record.'],
            ]);
        }
    }

    public function upsertDeliveryPrice(Request $request)
    {
        return response()->json([
            'message' => 'Not implemented yet.',
        ], 501);
    }

}
