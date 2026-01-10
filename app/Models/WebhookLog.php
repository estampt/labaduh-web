<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $fillable = ['provider','event_type','provider_event_id','payload'];

    protected $casts = ['payload' => 'array'];
}
