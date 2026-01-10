<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopDailyCapacity extends Model
{
    protected $fillable = ['shop_id','date','max_orders','max_kg','orders_reserved','kg_reserved'];

    protected $casts = [
        'date' => 'date',
        'max_orders' => 'integer',
        'orders_reserved' => 'integer',
        'max_kg' => 'decimal:2',
        'kg_reserved' => 'decimal:2',
    ];

    public function shop() { return $this->belongsTo(VendorShop::class, 'shop_id'); }
}
