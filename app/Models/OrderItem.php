<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'service_id',

        // ✅ snapshot fields (new)
        'service_name',
        'service_description',

        'qty',
        'uom',
        'pricing_model',
        'minimum',
        'min_price',
        'price_per_uom',
        'computed_price',
    ];

    protected $casts = [
        'qty' => 'float',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(OrderItemOption::class, 'order_item_id');
    }

    // ❌ removed: service() relationship
}
