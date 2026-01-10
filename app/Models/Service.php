<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Service extends Model { protected $fillable = ['name','base_unit','is_active']; public function options() { return $this->hasMany(ServiceOption::class); } }
