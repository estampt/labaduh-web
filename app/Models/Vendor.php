<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Vendor extends Model
{
    protected $fillable = ['name','email','phone','approval_status','approved_at','approved_by','is_active','customers_serviced_count','kilograms_processed_total','rating_avg','rating_count','documents_submitted_at','documents_verified_at'];
    protected $casts = ['approved_at'=>'datetime','is_active'=>'boolean'];
    public function shops() { return $this->hasMany(VendorShop::class); }
    public function documents() { return $this->hasMany(VendorDocument::class); }
    public function reviews() { return $this->hasMany(VendorReview::class); }
    public function visibleReviews() { return $this->hasMany(VendorReview::class)->where('is_visible', true); }
    public function services() { return $this->hasMany(VendorService::class); }
    public function deliveryPricingRules() { return $this->hasMany(DeliveryPricingRule::class); }
    public function users() { return $this->hasMany(User::class); }
    public function isApproved(): bool { return $this->approval_status === 'approved' && $this->is_active === true; }
    public function shops()
    {
        return $this->hasMany(\App\Models\VendorShop::class);
    }
}
