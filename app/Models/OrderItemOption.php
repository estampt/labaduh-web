<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemOption extends Model
{
    protected $fillable = [
        'order_item_id',
        'service_option_id',

        // ✅ snapshot fields (new)
        'service_option_name',
        'service_option_description',

        'price',
        'is_required',
        'computed_price',
    ];

    protected $casts = [
        'is_required' => 'bool',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    // ❌ removed: serviceOption() relationship
}
