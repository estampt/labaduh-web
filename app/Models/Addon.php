<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    protected $fillable = [
        'name',
        'group_key',
        'description',
        'price',
        'price_type',
        'is_required',
        'is_multi_select',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_required' => 'boolean',
        'is_multi_select' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
