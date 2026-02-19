<?php

namespace App\Models;

use App\Models\User;
use App\Observers\OrderObserver;
use App\Models\MediaAttachment;

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

    public function acceptedShop()
    {
        return $this->belongsTo(VendorShop::class, 'accepted_shop_id');
    }


    public function driver()
    {
        // ✅ adjust FK name to match your orders table
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }


    public function media()
    {
        return $this->morphMany(MediaAttachment::class, 'owner');
    }


/*
    protected static function booted(): void
    {
        static::observe(OrderObserver::class);
    }

    protected static function booted(): void
    {
        static::updated(function (Order $order) {

            // ✅ Use PHP error_log so it shows even when Laravel logs don't write
            error_log("Order model updated: id={$order->id}, old={$order->getOriginal('status')}, new={$order->status}");

            // Only if status changed
            if (!$order->wasChanged('status')) return;

            // Send push to customer
            app(\App\Services\PushNotificationService::class)->sendToUser(
                (int) $order->customer_id,
                'Order Update',
                "Your order is now {$order->status}.",
                [
                    'type' => 'order_update',
                    'route' => "/c/orders/{$order->id}",
                    'order_id' => (int) $order->id,
                    'status' => (string) $order->status,
                ]
            );
        });
    }
*/

}
