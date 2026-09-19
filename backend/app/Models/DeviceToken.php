<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// One installed app that can receive push notifications (an FCM registration token).
class DeviceToken extends Model
{
    public const PLATFORMS = ['android', 'ios', 'web'];

    public const APPS = ['customer', 'delegate', 'admin'];

    protected $fillable = ['user_id', 'token', 'platform', 'app', 'device_name', 'last_seen_at'];

    // The token is a credential for pushing to a phone; it is never echoed back.
    protected $hidden = ['token'];

    protected $casts = ['last_seen_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id');
    }
}
