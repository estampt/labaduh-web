<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'device_id',
        'last_seen_at',
    ];
}
