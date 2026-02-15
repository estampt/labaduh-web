<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderBroadcast extends Model
{
    protected $fillable = [
        'order_id',
        'shop_id',
        'vendor_id',
        'status',
        'sent_at',
        'expires_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function shop()
    {
        return $this->belongsTo(VendorShop::class, 'shop_id');
    }
}
