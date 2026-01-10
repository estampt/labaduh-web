<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PickupSlot extends Model
{
    protected $fillable = ['vendor_id','time_start','time_end','max_orders','is_active'];
}
