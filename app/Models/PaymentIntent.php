<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentIntent extends Model
{
    protected $fillable = [
        'purpose','order_id','vendor_id','customer_id','amount','currency','status',
        'provider','provider_intent_id','provider_payload','checkout_url','expires_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'provider_payload' => 'array',
        'expires_at' => 'datetime',
    ];

    public function payments() { return $this->hasMany(Payment::class); }
}
