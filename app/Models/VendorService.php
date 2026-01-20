<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorService extends Model
{
    protected $fillable = [
        'vendor_id',
        'service_id',

        // enablement
        'is_enabled',
        'use_default_pricing',

        // vendor-level overrides (nullable)
        'pricing_model', // per_kg_min | per_piece
        'min_kg',
        'rate_per_kg',
        'rate_per_piece',

        // legacy fields (keep only while DB still has them)
        'min_weight_kg',
        'base_price',
        'price_per_extra_kg',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'use_default_pricing' => 'boolean',

        'min_kg' => 'decimal:2',
        'rate_per_kg' => 'decimal:2',
        'rate_per_piece' => 'decimal:2',

        'min_weight_kg' => 'decimal:2',
        'base_price' => 'decimal:2',
        'price_per_extra_kg' => 'decimal:2',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeForVendor($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }
}
