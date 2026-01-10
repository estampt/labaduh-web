<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = [
        'order_id','vendor_id','shop_id','driver_id','type','status','scheduled_at',
        'pickup_lat','pickup_lng','dropoff_lat','dropoff_lng','distance_km','fee'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function shop() { return $this->belongsTo(VendorShop::class, 'shop_id'); }
    public function driver() { return $this->belongsTo(Driver::class); }
    public function events() { return $this->hasMany(DeliveryEvent::class); }
}
