<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = [
        'user_id','status','phone','vehicle_type','plate_no','city_id',
        'last_lat','last_lng','last_seen_at'
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function city() { return $this->belongsTo(City::class); }
}
