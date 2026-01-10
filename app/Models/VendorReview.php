<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorReview extends Model
{
    protected $fillable = [
        'vendor_id','customer_id','order_id','rating','comment','is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function customer() { return $this->belongsTo(User::class, 'customer_id'); }
}
