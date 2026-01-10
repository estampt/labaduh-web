<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DeliveryPricingRule extends Model { protected $fillable = ['vendor_id','base_fee','per_km_rate','min_fee','max_fee','is_active']; }
