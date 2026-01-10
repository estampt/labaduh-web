<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VendorAvailability extends Model
{
    protected $table = 'vendor_availability';
    protected $fillable = ['vendor_id','day_of_week','open_time','close_time','is_closed'];
}
