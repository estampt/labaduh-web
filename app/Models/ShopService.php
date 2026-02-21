<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopService extends Model
{
    protected $fillable = [
        'shop_id',
        'service_id',
        'pricing_model',
        'uom',
        'minimum',
        'min_price',
        'price_per_uom',
        'is_active',
        'currency',
        'sort_order',
    ];

    protected $casts = [
        'minimum' => 'decimal:2',
        'min_price' => 'decimal:2',
        'price_per_uom' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(VendorShop::class, 'shop_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }


    public function options()
    {
        return $this->belongsToMany(\App\Models\ServiceOption::class, 'shop_service_options', 'shop_service_id', 'service_option_id')
            ->withPivot(['price','is_active','sort_order'])
            ->withTimestamps()
            ->orderBy('shop_service_options.sort_order');
    }


}
