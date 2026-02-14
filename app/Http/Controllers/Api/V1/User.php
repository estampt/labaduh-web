<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',

        'role',
        'vendor_id',

        'contact_number',

        'address_line1',
        'address_line2',
        'postal_code',
        'country_id',
        'state_province_id',
        'city_id',
        'latitude',
        'longitude',

        'facebook_id',
        'google_id',
        'twitter_id',
        'apple_id',

        'badge',
        'phone_verified_at',

        'email_otp_hash',
        'email_otp_expires_at',
        'phone_otp_hash',
        'phone_otp_expires_at',
        'is_verified',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_otp_hash',
        'phone_otp_hash',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'email_otp_expires_at' => 'datetime',
        'phone_otp_expires_at' => 'datetime',
        'is_verified' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'last_seen_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function pushTokens()
    {
        return $this->hasMany(PushToken::class);
    }

    /**
     * Used by Notifications to route FCM pushes for this user.
     * Returns a list of device tokens (strings).
     */
    public function routeNotificationForFcm(): array
    {
        return $this->pushTokens()
            ->whereNotNull('token')
            ->pluck('token')
            ->unique()
            ->values()
            ->all();
    }
}
