<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopServicePrice extends Model
{
    use HasFactory;

    protected $table = 'shop_service_prices';

    protected $fillable = [
        'shop_id',
        'service_id',
        'category_code',
        'pricing_model',
        'price_per_kg',
        'min_kg',
        'block_kg',
        'block_price',
        'flat_price',
        'is_active',
    ];

    protected $casts = [
        'price_per_kg' => 'decimal:2',
        'min_kg' => 'decimal:2',
        'block_kg' => 'decimal:2',
        'block_price' => 'decimal:2',
        'flat_price' => 'decimal:2',
        'is_active' => 'bool',
    ];

    public function shop()
    {
        return $this->belongsTo(VendorShop::class, 'shop_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
