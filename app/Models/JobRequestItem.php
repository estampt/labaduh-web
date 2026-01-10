<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobRequestItem extends Model
{
    protected $fillable = ['job_request_id','service_id','category_code','category_label','bag_count','weight_kg','min_kg_applied','options','price_snapshot'];

    protected $casts = ['options' => 'array','weight_kg' => 'decimal:2'];

    public function jobRequest() { return $this->belongsTo(JobRequest::class); }
}
