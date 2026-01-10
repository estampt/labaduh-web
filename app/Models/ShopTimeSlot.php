<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopTimeSlot extends Model
{
    protected $fillable = ['shop_id','slot_type','day_of_week','time_start','time_end','is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function shop() { return $this->belongsTo(VendorShop::class, 'shop_id'); }
}
