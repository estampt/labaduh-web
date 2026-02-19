<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class MediaAttachment extends Model
{
    /**
     * Table (optional since Laravel auto-detects)
     */
    protected $table = 'media_attachments';

    /**
     * Mass assignable fields
     */
    protected $fillable = [
        'owner_type',
        'owner_id',
        'disk',
        'path',
        'mime',
        'size_bytes',
        'category',
    ];

    /**
     * Auto-append URL when serialized
     */
    protected $appends = [
        'url',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Polymorphic owner
     */
    public function owner()
    {
        return $this->morphTo();
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Public URL accessor
     */
    public function getUrlAttribute(): ?string
    {
        if (!$this->path) {
            return null;
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Filter by category
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /*
    |--------------------------------------------------------------------------
    | Category Constants
    |--------------------------------------------------------------------------
    | Prevents typo bugs across system
    */

    public const CATEGORY_WEIGHT_REVIEW   = 'weight_review';
    public const CATEGORY_PRICING_UPDATE  = 'pricing_update';
    public const CATEGORY_PICKUP_PROOF    = 'pickup_proof';
    public const CATEGORY_DELIVERY_PROOF  = 'delivery_proof';
    public const CATEGORY_CHAT_IMAGE      = 'chat_image';
}
