<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrderItem extends Model { protected $fillable = ['order_id','vendor_service_id','weight_kg','items','service_charge','options_total','line_total']; public function options(){ return $this->hasMany(OrderItemOption::class);} }
