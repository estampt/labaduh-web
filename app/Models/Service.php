<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'name',
        'description',
        'base_unit', // kg | item | order
        'is_active',
        'icon',

        // System default pricing
        'default_pricing_model', // per_kg_min | per_piece
        'default_min_kg',
        'default_rate_per_kg',
        'default_rate_per_piece',

        // Policy
        'allow_vendor_override_price',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allow_vendor_override_price' => 'boolean',

        // decimals
        'default_min_kg' => 'decimal:2',
        'default_rate_per_kg' => 'decimal:2',
        'default_rate_per_piece' => 'decimal:2',
    ];

    public function options()
    {
        return $this->hasMany(ServiceOption::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }



}
