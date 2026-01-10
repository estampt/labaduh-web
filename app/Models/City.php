<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $table = 'cities';

    protected $fillable = [
        'state_province_id','name','latitude','longitude','wikiDataId'
    ];

    public function stateProvince()
    {
        return $this->belongsTo(StateProvince::class, 'state_province_id');
    }

    public function country()
    {
        // Convenience relationship via state_province
        return $this->hasOneThrough(
            Country::class,
            StateProvince::class,
            'id',          // Foreign key on state_province...
            'id',          // Foreign key on countries...
            'state_province_id', // Local key on cities...
            'country_id'   // Local key on state_province...
        );
    }

    public function shops()
    {
        return $this->hasMany(VendorShop::class, 'city_id');
    }
}
