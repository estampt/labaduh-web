<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderBroadcast extends Model
{
    protected $fillable = [
        'order_id','vendor_id','shop_id','status','quoted_total'
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items() { return $this->hasMany(\App\Models\OrderItem::class); }

}
