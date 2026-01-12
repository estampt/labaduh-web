<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorDocument extends Model
{
    protected $fillable = [
        'vendor_id',
        'type',
        'file_path',
        'status',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
