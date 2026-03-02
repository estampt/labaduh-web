<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'provider',
        'method',
        'status',
        'currency',
        'amount_estimated',
        'amount_authorized',
        'amount_final',
        'amount_captured',
        'amount_refunded',
        'provider_ref',
        'provider_ref2',
        'client_secret',
        'idempotency_key',
        'dedupe_key',
        'expires_at',
        'authorized_at',
        'paid_at',
        'failed_at',
        'metadata',
        'last_error',
    ];

    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'authorized_at' => 'datetime',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'amount_estimated' => 'integer',
        'amount_authorized' => 'integer',
        'amount_final' => 'integer',
        'amount_captured' => 'integer',
        'amount_refunded' => 'integer',
    ];

    // ---- Relationships ----

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    // ---- Convenience helpers (optional) ----

    public function isStripe(): bool
    {
        return $this->provider === 'stripe';
    }

    public function isXendit(): bool
    {
        return $this->provider === 'xendit';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function markAuthorized(): void
    {
        $this->status = 'authorized';
        $this->authorized_at = now();
        $this->save();
    }

    public function markPaid(): void
    {
        $this->status = 'paid';
        $this->paid_at = now();
        $this->save();
    }

    public function markFailed(?string $error = null): void
    {
        $this->status = 'failed';
        $this->failed_at = now();
        $this->last_error = $error;
        $this->save();
    }
}
