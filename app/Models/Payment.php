<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'payment_intent_id','amount','currency','method','status','provider','provider_payment_id','paid_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function intent() { return $this->belongsTo(PaymentIntent::class, 'payment_intent_id'); }
}
