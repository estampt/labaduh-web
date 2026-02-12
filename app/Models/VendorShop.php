<?php

namespace App\Models;

use App\Models\OrderFeedback;
use Illuminate\Database\Eloquent\Model;

class VendorShop extends Model
{
    protected $table = 'vendor_shops';

    protected $fillable = [
      'vendor_id',
      'name','phone','profile_photo_url',
      'address_line1','address_line2','postal_code',
      'country_id','state_province_id','city_id',
      'latitude','longitude',
      'default_max_orders_per_day','default_max_kg_per_day',
      'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    // Location master tables (matches your migrations)
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function stateProvince()
    {
        return $this->belongsTo(StateProvince::class, 'state_province_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    // Convenience (through relations)
    public function region()
    {
        return $this->hasOneThrough(
            Region::class,
            Country::class,
            'id',        // FK on countries...
            'id',        // FK on regions...
            'country_id',// local key on vendor_shops...
            'region_id'  // local key on countries...
        );
    }

    public function shopServices()
    {
        return $this->hasMany(\App\Models\ShopService::class, 'shop_id');
    }

    public function feedbacks()
    {
        return $this->hasMany(OrderFeedback::class, 'vendor_shop_id');
    }

}

