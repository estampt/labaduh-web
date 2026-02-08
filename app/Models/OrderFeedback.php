<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderFeedback extends Model
{
    protected $table = 'order_feedbacks'; // ✅ force correct table

    protected $fillable = [
        'order_id',
        'vendor_shop_id',
        'customer_id',
        'rating',
        'comments',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function images()
    {
        return $this->hasMany(OrderFeedbackImage::class, 'order_feedback_id')
            ->orderBy('sort_order');
    }
}

