<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceOption extends Model
{
    protected $table = 'service_options';
    protected $fillable = [
        'service_id',
        'name',
        'description',
        'kind',
        'group_key',
        'price',
        'price_type',
        'is_required',
        'is_multi_select',
        'is_default_selected',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_required' => 'boolean',
        'is_multi_select' => 'boolean',
        'is_default_selected' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
