<?php

namespace App\Services;

use App\Models\Vendor;
use App\Services\PricingResolverService;

class VendorScoringService
{
    public function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        if (!$lat2 && !$lon2) return 9999.0;
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earth * $c;
    }

    public function workloadBadge(float $remainingKg, float $maxKg): string
    {
        if ($maxKg <= 0) return 'unknown';
        $ratio = $remainingKg / $maxKg;
        return match (true) {
            $ratio > 0.60 => 'light',
            $ratio > 0.30 => 'normal',
            $ratio > 0.10 => 'busy',
            default => 'critical',
        };
    }

    public function estimatePricing(int $vendorId, int $shopId, float $distanceKm, float $kg, ?array $items = null): array
    {
        $resolver = app(PricingResolverService::class);

        $deliveryRule = $resolver->resolveDeliveryRule($vendorId, $shopId);
        $deliveryFee = round(($deliveryRule['base_fee'] ?? 0) + (max(0, $distanceKm) * ($deliveryRule['fee_per_km'] ?? 0)), 2);

        $lines = [];
        $subtotal = 0.0;

        if ($items && is_array($items) && count($items) > 0) {
            foreach ($items as $it) {
                $serviceId = (int) ($it['service_id'] ?? 0);
                $category = $it['category_code'] ?? null;

                $enteredKg = (float) ($it['entered_kg'] ?? $it['weight_kg'] ?? 0);
                $rule = $resolver->resolveServiceRule($vendorId, $shopId, $serviceId, $category);

                [$billedKg, $lineSubtotal] = $this->computeLine($rule, $enteredKg);

                $subtotal += $lineSubtotal;

                $lines[] = [
                    'service_id' => $serviceId,
                    'category_code' => $category,
                    'category_label' => $it['category_label'] ?? null,
                    'entered_kg' => $enteredKg,
                    'billed_kg' => $billedKg,
                    'line_subtotal' => $lineSubtotal,
                    'pricing_rule' => $rule,
                ];
            }
        } else {
            // Backward compatibility: treat as one line with service_id=0
            $rule = [
                'source' => 'system',
                'pricing_model' => 'per_kg_min',
                'min_kg' => (float) config('pricing.min_kg_per_line', 6.0),
                'rate_per_kg' => (float) config('pricing.rate_per_kg', 8.0),
                'block_kg' => null,
                'block_price' => null,
                'flat_price' => null,
            ];
            [$billedKg, $lineSubtotal] = $this->computeLine($rule, (float)$kg);
            $subtotal = $lineSubtotal;
            $lines[] = [
                'service_id' => 0,
                'category_code' => null,
                'category_label' => null,
                'entered_kg' => (float)$kg,
                'billed_kg' => $billedKg,
                'line_subtotal' => $lineSubtotal,
                'pricing_rule' => $rule,
            ];
        }

        $total = round($subtotal + $deliveryFee, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'delivery_fee' => $deliveryFee,
            'total' => $total,
            'lines' => $lines,
            'delivery_rule' => $deliveryRule,
        ];
    }

    private function computeLine(array $rule, float $enteredKg): array
    {
        $model = $rule['pricing_model'] ?? 'per_kg_min';

        if ($model === 'flat') {
            $lineSubtotal = (float) ($rule['flat_price'] ?? 0);
            return [0.0, round($lineSubtotal, 2)];
        }

        if ($model === 'per_block') {
            $blockKg = (float) ($rule['block_kg'] ?? 6.0);
            $blockPrice = (float) ($rule['block_price'] ?? 0);
            $blocks = $blockKg > 0 ? (int) ceil($enteredKg / $blockKg) : 0;
            $billedKg = $blocks * $blockKg;
            $lineSubtotal = $blocks * $blockPrice;
            return [round($billedKg, 2), round($lineSubtotal, 2)];
        }

        // per_kg_min
        $minKg = (float) ($rule['min_kg'] ?? config('pricing.min_kg_per_line', 6.0));
        $rate = (float) ($rule['rate_per_kg'] ?? config('pricing.rate_per_kg', 8.0));
        $billedKg = max($minKg, $enteredKg);
        $lineSubtotal = $billedKg * $rate;
        return [round($billedKg, 2), round($lineSubtotal, 2)];
    }
    public function score(array $ctx): float
    {
        /** @var Vendor $vendor */
        $vendor = $ctx['vendor'];
        $distance = (float)$ctx['distance_km'];
        $cap = $ctx['capacity'];
        $pricing = $ctx['pricing'];

        $distanceScore = max(0, 100 - (($distance / 15.0) * 100));
        $capacityScore = $this->capacityScore((float)$cap['remaining_kg'], (float)$cap['max_kg']);
        $ratingScore = min(100, max(0, ((float)$vendor->rating_avg / 5.0) * 100));
        $priceScore = max(0, 100 - (((float)$pricing['total'] / 500.0) * 100));
        $tier = $vendor->subscription_tier ?? 'free';
        $tierCfg = config('monetization.subscription_tiers.' . $tier, []);
        $subscriptionScore = match ($tier) {
            'elite' => 100,
            'pro' => 70,
            default => 0,
        };

        $scoreBoostPct = (float)($tierCfg['score_boost_percent'] ?? 0);

        $reliabilityScore = 60;

        $base = (
            ($distanceScore * 0.25) +
            ($capacityScore * 0.20) +
            ($ratingScore * 0.15) +
            ($priceScore * 0.15) +
            ($subscriptionScore * 0.15) +
            ($reliabilityScore * 0.10),
        );

        $final = $base * (1 + ($scoreBoostPct / 100.0));
        return round($final, 2);
    }

    private function capacityScore(float $remainingKg, float $maxKg): float
    {
        if ($maxKg <= 0) return 0;
        $ratio = $remainingKg / $maxKg;
        return match (true) {
            $ratio > 0.60 => 100,
            $ratio > 0.30 => 70,
            $ratio > 0.10 => 40,
            default => 10,
        };
    }
}
