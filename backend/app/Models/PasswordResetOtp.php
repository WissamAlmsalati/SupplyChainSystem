<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordResetOtp extends Model
{
    use HasFactory;

    protected $table = 'password_reset_otps';

    protected $fillable = [
        'mobile_number',
        'token',
        'otp',
        'payload',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'payload' => 'array',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
