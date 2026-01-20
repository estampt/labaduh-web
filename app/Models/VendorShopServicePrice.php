<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class VendorShopServicePrice extends Model
{
    protected $fillable = [
        'shop_id',
        'service_id',
        'is_enabled',
        'use_vendor_default_pricing',

        'pricing_model', // per_kg_min | per_piece
        'min_kg',
        'rate_per_kg',
        'rate_per_piece',

        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_enabled' => 'boolean',
        'use_vendor_default_pricing' => 'boolean',

        'min_kg' => 'decimal:2',
        'rate_per_kg' => 'decimal:2',
        'rate_per_piece' => 'decimal:2',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function shop()
    {
        return $this->belongsTo(VendorShop::class, 'shop_id');
    }

    /** Rows effective on a given date (NULL bounds are open-ended). */
    public function scopeEffectiveOn(Builder $query, $date): Builder
    {
        $d = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();

        return $query
            ->where(function ($q) use ($d) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $d);
            })
            ->where(function ($q) use ($d) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $d);
            });
    }

    /**
     * Overlap allowed:
     * Pick most specific / newest:
     * - Prefer non-null effective_from
     * - Higher effective_from wins
     * - Latest created_at breaks ties
     */
    public function scopeBestMatch(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN effective_from IS NULL THEN 1 ELSE 0 END') // non-null first
            ->orderByDesc('effective_from')
            ->orderByDesc('created_at');
    }
}
