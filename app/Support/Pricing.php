<?php

namespace App\Support;

final class Pricing
{
    /**
     * Universal computed price formula:
     *
     * computed_price = min_price + ((qtyEff - minimum) * price_per_uom)
     * where qtyEff = (qty < minimum) ? minimum : qty
     */
    public static function computeComputedPrice(
        float $qty,
        ?float $minimum,
        ?float $minPrice,
        ?float $pricePerUom,
        int $precision = 2
    ): float {
        $min = max(0.0, (float) ($minimum ?? 0));
        $base = max(0.0, (float) ($minPrice ?? 0));
        $ppu = max(0.0, (float) ($pricePerUom ?? 0));

        $qtyEff = max(0.0, $qty);
        if ($min > 0) {
            $qtyEff = max($qtyEff, $min);
        }

        $computed = $base + (($qtyEff - $min) * $ppu);

        return round(max(0.0, $computed), $precision);
    }

    /**
     * Build your "service_items" snapshot structure.
     * (Matches the exact fields you listed.)
     */
    public static function makeServiceItemSnapshot(array $it, ?object $serviceRow = null): array
    {
        $qty = (float) ($it['qty'] ?? 0);

        $minimum = array_key_exists('minimum', $it) ? (float) $it['minimum'] : null;
        $minPrice = array_key_exists('min_price', $it) ? (float) $it['min_price'] : null;
        $ppu = array_key_exists('price_per_uom', $it) ? (float) $it['price_per_uom'] : null;

        $computed = self::computeComputedPrice(
            qty: $qty,
            minimum: $minimum,
            minPrice: $minPrice,
            pricePerUom: $ppu
        );

        return [
            // ✅ NEW: snapshot fields stored on order_items
            'service_name' => $serviceRow?->name ?? ($it['service_name'] ?? null),
            'service_description' => $serviceRow?->description ?? ($it['service_description'] ?? null),

            'qty' => $qty,
            'qty_estimated' => $qty,
            'qty_actual' => $qty,
            'uom' => $it['uom'] ?? null,
            'pricing_model' => $it['pricing_model'] ?? null,
            'minimum' => $minimum,
            'min_price' => $minPrice,
            'price_per_uom' => $ppu,

            // ✅ computed using backend formula
            'computed_price' => $computed,
            'estimated_price' => $computed,
            'final_price' => $computed,
        ];
    }
}
