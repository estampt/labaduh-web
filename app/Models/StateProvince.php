<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StateProvince extends Model
{
    protected $table = 'state_province';

    protected $fillable = [
        'country_id','name','state_code','latitude','longitude','wikiDataId'
    ];

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function cities()
    {
        return $this->hasMany(City::class, 'state_province_id');
    }

    public function shops()
    {
        return $this->hasMany(VendorShop::class, 'state_province_id');
    }
}
