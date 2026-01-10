<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobRequest extends Model
{
    protected $fillable = [
        'customer_id','pickup_lat','pickup_lng','dropoff_lat','dropoff_lng',
        'pickup_date','pickup_time_start','pickup_time_end',
        'delivery_date','delivery_time_start','delivery_time_end',
        'estimated_kg','assignment_status','assigned_vendor_id','assigned_shop_id',
        'match_snapshot','notes',
    ];

    protected $casts = [
        'pickup_date' => 'date',
        'delivery_date' => 'date',
        'match_snapshot' => 'array',
    ];

    public function items() { return $this->hasMany(JobRequestItem::class); }
    public function customer() { return $this->belongsTo(User::class, 'customer_id'); }
}
