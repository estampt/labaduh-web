<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderTimelineEvent extends Model
{
    protected $table = 'order_timeline_events';

    protected $fillable = [
        'order_id',
        'key',
        'actor_type',
        'actor_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
