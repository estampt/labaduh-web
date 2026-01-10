<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorDeliveryPrice extends Model
{
    protected $fillable = ['vendor_id','shop_id','base_fee','fee_per_km','is_active'];

    protected $casts = [
        'base_fee' => 'decimal:2',
        'fee_per_km' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function shop() { return $this->belongsTo(VendorShop::class, 'shop_id'); }
}
