<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderFeedbackImage extends Model
{
    protected $table = 'order_feedback_images'; // ✅ force correct table

    protected $fillable = [
        'order_feedback_id',
        'image_url',
        'sort_order',
    ];

    public function feedback()
    {
        return $this->belongsTo(OrderFeedback::class, 'order_feedback_id');
    }
}

