<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = 'countries';

    protected $fillable = [
        'region_id','name','iso2','iso3','numeric_code','phone_code','capital',
        'currency','currency_name','currency_symbol','tld','native','nationality',
        'timezones','latitude','longitude','emoji','emojiU','flag','wikiDataId'
    ];

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function states()
    {
        return $this->hasMany(StateProvince::class, 'country_id');
    }

    public function shops()
    {
        return $this->hasMany(VendorShop::class, 'country_id');
    }
}
