<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageThread extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'scope',
        'order_id',
        'shop_id',
        'customer_user_id',
        'vendor_user_id',
        'locked_at',
        'last_message_at',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
        'last_message_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'thread_id');
    }

    public function isLocked(): bool
    {
        return !is_null($this->locked_at);
    }
}
