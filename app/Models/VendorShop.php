<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorShop extends Model
{
    protected $table = 'vendor_shops';

    protected $fillable = [
        'vendor_id',
        'name','phone','address',
        'latitude','longitude','service_radius_km',
        'country_id','state_province_id','city_id',
        'address_line1',
        'address_line2',
        'is_active'
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
}

