<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $table = 'regions';

    protected $fillable = ['continent_id','name','code','wikiDataId'];

    public function continent()
    {
        return $this->belongsTo(Continent::class, 'continent_id');
    }

    public function countries()
    {
        return $this->hasMany(Country::class, 'region_id');
    }
}
