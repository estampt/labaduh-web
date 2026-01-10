<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Continent extends Model
{
    protected $table = 'continents';

    protected $fillable = ['name', 'code', 'wikiDataId'];

    public function regions()
    {
        return $this->hasMany(Region::class, 'continent_id');
    }
}
