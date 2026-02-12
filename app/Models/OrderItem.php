<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id','service_id','qty','uom',
        'pricing_model','minimum','min_price','price_per_uom',
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
        return $this->hasMany(OrderItemOption::class);
    }
    public function service()
{
    return $this->belongsTo(Service::class, 'service_id');
}
}
