<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorSubscription extends Model
{
    protected $fillable = [
        'vendor_id','plan_id','status','starts_at','ends_at','cancelled_at','provider','provider_subscription_id'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function plan() { return $this->belongsTo(SubscriptionPlan::class, 'plan_id'); }
    public function invoices() { return $this->hasMany(SubscriptionInvoice::class); }
}
