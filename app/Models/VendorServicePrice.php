<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorServicePrice extends Model
{
    protected $fillable = [
        'vendor_id','shop_id','service_id','category_code','pricing_model',
        'min_kg','rate_per_kg','block_kg','block_price','flat_price','is_active'
    ];

    protected $casts = [
        'min_kg' => 'decimal:2',
        'rate_per_kg' => 'decimal:2',
        'block_kg' => 'decimal:2',
        'block_price' => 'decimal:2',
        'flat_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function shop() { return $this->belongsTo(VendorShop::class, 'shop_id'); }
}
