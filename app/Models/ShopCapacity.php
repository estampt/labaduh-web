<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopCapacity extends Model
{
    use HasFactory;

    protected $table = 'shop_capacities';

    protected $fillable = [
        'shop_id',
        'date',
        'max_orders',
        'max_kg',
        'booked_orders',
        'booked_kg',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'max_kg' => 'decimal:2',
        'booked_kg' => 'decimal:2',
    ];

    public function shop()
    {
        return $this->belongsTo(VendorShop::class, 'shop_id');
    }
}
