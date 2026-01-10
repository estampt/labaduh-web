<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VendorService extends Model { protected $fillable = ['vendor_id','service_id','min_weight_kg','base_price','price_per_extra_kg','is_enabled']; }
