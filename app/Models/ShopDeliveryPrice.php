<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopDeliveryPrice extends Model
{
    use HasFactory;

    protected $table = 'shop_delivery_prices';

    protected $fillable = [
        'shop_id',
        'base_fee',
        'per_km_fee',
        'min_fee',
        'max_fee',
    ];

    protected $casts = [
        'base_fee' => 'decimal:2',
        'per_km_fee' => 'decimal:2',
        'min_fee' => 'decimal:2',
        'max_fee' => 'decimal:2',
    ];

    public function shop()
    {
        return $this->belongsTo(VendorShop::class, 'shop_id');
    }
}
