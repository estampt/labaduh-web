<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopServiceOption extends Model
{
    protected $fillable = [
        'shop_service_id',
        'service_option_id',
        'price',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function shopService()
    {
        return $this->belongsTo(ShopService::class, 'shop_service_id');
    }

    public function serviceOption()
    {
        return $this->belongsTo(ServiceOption::class, 'service_option_id');
    }


}
