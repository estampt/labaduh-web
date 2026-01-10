<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionInvoice extends Model
{
    protected $fillable = [
        'vendor_subscription_id','invoice_no','amount','currency','status','provider','provider_invoice_id','paid_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function subscription() { return $this->belongsTo(VendorSubscription::class, 'vendor_subscription_id'); }
}
