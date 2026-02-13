<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Vendor extends Model
{

    const STATUS_PENDING     = 'pending';
    const STATUS_APPROVED    = 'approved';
    const STATUS_REJECTED    = 'rejected';
    const STATUS_SUSPENDED   = 'suspended';
    const STATUS_DEACTIVATED = 'deactivated';

    public function scopeApproved($q)
    {
        return $q->where('status', self::STATUS_APPROVED);
    }


    protected $fillable = ['id','name','email','phone','approval_status','approved_at','approved_by','is_active','customers_serviced_count','kilograms_processed_total','rating_avg','rating_count','documents_submitted_at','documents_verified_at'];
    //protected $casts = ['approved_at'=>'datetime',
    //    'last_seen_at' => 'datetime','is_active'=>'boolean'];

    protected $casts = [
        // ...your existing casts
        'last_seen_at' => 'datetime',
        'email_otp_expires_at' => 'datetime',
        'phone_otp_expires_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
    ];

    public function shops() { return $this->hasMany(VendorShop::class); }
    public function documents() { return $this->hasMany(VendorDocument::class); }
    public function reviews() { return $this->hasMany(VendorReview::class); }
    public function visibleReviews() { return $this->hasMany(VendorReview::class)->where('is_visible', true); }
    public function services() { return $this->hasMany(VendorService::class); }
    public function deliveryPricingRules() { return $this->hasMany(DeliveryPricingRule::class); }
    public function users() { return $this->hasMany(User::class); }
    public function isApproved(): bool { return $this->approval_status === 'approved' && $this->is_active === true && $this->status === 'approved'; }
    public function user()
    {
        // users.vendor_id -> vendors.id
        return $this->hasOne(\App\Models\User::class, 'vendor_id', 'id');
    }


}
