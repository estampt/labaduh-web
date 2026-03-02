<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvent extends Model
{
    protected $fillable = [
        'payment_id',
        'provider',
        'event_type',
        'provider_event_id',
        'signature_verified',
        'payload_hash',
        'idempotency_key',
        'status_before',
        'status_after',
        'amount',
        'currency',
        'payload',
        'notes',
    ];

    protected $casts = [
        'payload' => 'array',
        'amount' => 'integer',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
