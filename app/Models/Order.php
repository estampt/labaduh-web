<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'customer_id','status',
        'search_lat','search_lng','radius_km',
        'pickup_mode','pickup_window_start','pickup_window_end',
        'delivery_mode',
        'pickup_address_id','delivery_address_id',
        'pickup_address_snapshot','delivery_address_snapshot',
        'currency','subtotal','delivery_fee','service_fee','discount','total',
        'accepted_vendor_id','accepted_shop_id',
        'notes',
        'pricing_status','final_proposed_at','approved_at','auto_confirm_minutes',
        'estimated_subtotal','estimated_total','final_subtotal','final_total','pricing_notes',

    ];

    protected $casts = [
        'pickup_address_snapshot' => 'array',
        'delivery_address_snapshot' => 'array',
        'pickup_window_start' => 'datetime',
        'pickup_window_end' => 'datetime',
        'search_lat' => 'float',
        'search_lng' => 'float',
        'radius_km' => 'int',
        'pickup_driver_id' => 'int',
        'delivery_driver_id' => 'int',
        'final_proposed_at' => 'datetime',
        'approved_at' => 'datetime',
        'auto_confirm_minutes' => 'int',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function broadcasts(): HasMany
    {
        return $this->hasMany(OrderBroadcast::class);
    }

    public function timelineEvents()
    {
        return $this->hasMany(\App\Models\OrderTimelineEvent::class, 'order_id');
    }


}
