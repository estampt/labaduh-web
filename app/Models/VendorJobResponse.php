<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorJobResponse extends Model
{
    protected $fillable = ['job_offer_id','vendor_id','response','reason'];

    public function offer() { return $this->belongsTo(JobOffer::class, 'job_offer_id'); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
}
