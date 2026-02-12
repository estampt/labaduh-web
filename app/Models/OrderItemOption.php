<?php

namespace App\Models;

    use App\Models\ServiceOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemOption extends Model
{
    protected $fillable = [
        'order_item_id','service_option_id','price','is_required','computed_price'
    ];

    protected $casts = [
        'is_required' => 'bool',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }


    public function serviceOption()
    {
        return $this->belongsTo(ServiceOption::class, 'service_option_id');
    }

}
