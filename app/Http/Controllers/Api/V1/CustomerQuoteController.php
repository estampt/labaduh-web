<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ShopService;
use App\Models\VendorShop;
use Illuminate\Http\Request;

class CustomerQuoteController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'lat' => ['required','numeric'],
            'lng' => ['required','numeric'],
            'radius_km' => ['nullable','integer','min:1','max:50'],
            'items' => ['required','array','min:1'],
            'items.*.service_id' => ['required','integer','exists:services,id'],
            'items.*.qty' => ['required','numeric','min:0.01'],
            'items.*.option_ids' => ['nullable','array'],
            'items.*.option_ids.*' => ['integer','exists:service_options,id'],
        ]);

        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];
        $radius = (int) ($data['radius_km'] ?? 3);
        $items = collect($data['items']);

        // Nearby shops
        $shopIds = VendorShop::query()
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereRaw(
                "ST_Distance_Sphere(point(lng, lat), point(?, ?)) <= ?",
                [$lng, $lat, $radius * 1000]
            )
            ->pluck('id');

        // Group items by service
        $serviceIds = $items->pluck('service_id')->unique()->values();

        // Pull matching ShopService rows for these shops/services (with options)
        $shopServices = ShopService::query()
            ->whereIn('shop_id', $shopIds)
            ->whereIn('service_id', $serviceIds)
            ->where('is_active', true)
            ->with([
                'shop:id,vendor_id,lat,lng,name',
                'service:id,name,icon,base_unit',
                'options.serviceOption:id,name,price,is_required,is_active',
            ])
            ->get();

        // Build quotes per shop (sum across selected services)
        $byShop = [];

        foreach ($shopServices as $ss) {
            $shopKey = (int) $ss->shop_id;

            $selectedItem = $items->firstWhere('service_id', $ss->service_id);
            if (!$selectedItem) continue;

            $qty = (float) $selectedItem['qty'];
            $optionIds = collect($selectedItem['option_ids'] ?? []);

            $opts = $ss->options
                ->whereIn('service_option_id', $optionIds)
                ->where('is_active', true);

            // Add required options automatically if you want:
            $required = $ss->options
                ->filter(fn($x) => $x->serviceOption && $x->serviceOption->is_required)
                ->where('is_active', true);

            $allOpts = $opts->merge($required)->unique('service_option_id');

            $serviceTotal = $this->computeTieredMinPlus($ss, $qty);

            $optionsTotal = (float) $allOpts->sum(fn($o) => (float) $o->price);

            $lineTotal = round($serviceTotal + $optionsTotal, 2);

            if (!isset($byShop[$shopKey])) {
                $byShop[$shopKey] = [
                    'shop_id' => $ss->shop_id,
                    'vendor_id' => $ss->shop->vendor_id ?? null,
                    'shop' => $ss->shop,
                    'currency' => $ss->currency ?? 'SGD',
                    'subtotal' => 0,
                    'breakdown' => [],
                ];
            }

            $byShop[$shopKey]['subtotal'] += $lineTotal;

            $byShop[$shopKey]['breakdown'][] = [
                'service_id' => $ss->service_id,
                'service' => $ss->service,
                'qty' => $qty,
                'uom' => $ss->uom,
                'service_price' => round($serviceTotal, 2),
                'options' => $allOpts->values()->map(fn($o) => [
                    'service_option_id' => $o->service_option_id,
                    'name' => $o->serviceOption?->name,
                    'price' => $o->price,
                ]),
                'line_total' => $lineTotal,
            ];
        }

        $quotes = collect(array_values($byShop))->map(function ($q) {
            $q['subtotal'] = round((float) $q['subtotal'], 2);

            // Placeholder fees for now (you can swap formula later)
            $delivery = 49.00;
            $serviceFee = 15.00;

            $q['fees'] = [
                'delivery_fee' => $delivery,
                'service_fee' => $serviceFee,
            ];
            $q['total'] = round($q['subtotal'] + $delivery + $serviceFee, 2);

            return $q;
        })->values();

        return response()->json([
            'data' => [
                'quotes' => $quotes,
            ],
        ]);
    }

    private function computeTieredMinPlus(ShopService $ss, float $qty): float
    {
        $min = (float) ($ss->minimum ?? 0);
        $minPrice = (float) ($ss->min_price ?? 0);
        $ppu = (float) ($ss->price_per_uom ?? 0);

        if ($min <= 0) {
            // fallback: qty * ppu
            return round($qty * $ppu, 2);
        }

        if ($qty <= $min) return round($minPrice, 2);

        return round($minPrice + (($qty - $min) * $ppu), 2);
    }
}
