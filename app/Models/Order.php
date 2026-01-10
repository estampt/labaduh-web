<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'vendor_id','shop_id','customer_id',
        'pickup_lat','pickup_lng','dropoff_lat','dropoff_lng',
        'pickup_date','pickup_time_start','pickup_time_end',
        'status','distance_km','subtotal','delivery_fee','total',
        'stats_applied_at',
        'notes',
    ];

    protected $casts = [
        'pickup_date' => 'date',
        'stats_applied_at' => 'datetime',
    ];

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function shop() { return $this->belongsTo(VendorShop::class, 'shop_id'); }
    public function items() { return $this->hasMany(OrderItem::class); }
}
