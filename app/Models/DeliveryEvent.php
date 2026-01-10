<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryEvent extends Model
{
    protected $fillable = ['delivery_id','status','created_by','notes'];

    public function delivery() { return $this->belongsTo(Delivery::class); }
}
