<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorDocument extends Model
{
    protected $fillable = [
        'vendor_id','document_type','file_path','original_filename','mime_type','file_size',
        'uploaded_by','status','reviewed_by','reviewed_at','rejection_reason',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
