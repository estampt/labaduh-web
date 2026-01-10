<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrderItemOption extends Model { protected $fillable = ['order_item_id','service_option_id','charge']; }
