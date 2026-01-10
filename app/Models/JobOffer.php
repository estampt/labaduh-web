<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobOffer extends Model
{
    protected $fillable = ['job_request_id','vendor_id','shop_id','status','expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function jobRequest() { return $this->belongsTo(JobRequest::class); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function shop() { return $this->belongsTo(VendorShop::class, 'shop_id'); }
}
