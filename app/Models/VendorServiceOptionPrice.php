<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorServiceOptionPrice extends Model
{
    protected $table = 'vendor_service_option_prices';

    protected $fillable = [
        'vendor_id',
        'shop_id',
        'vendor_service_price_id',
        'service_option_id',
        'price',
        'price_type',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];


    public function serviceOption()
    {
        return $this->belongsTo(\App\Models\ServiceOption::class, 'service_option_id', 'id');
    }


    public function shop()
    {
        return $this->belongsTo(VendorShop::class, 'shop_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }


}
