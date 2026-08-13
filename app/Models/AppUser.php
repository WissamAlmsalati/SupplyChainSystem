<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\LogsActivity;

class AppUser extends Authenticatable
{
    use HasApiTokens, HasFactory, LogsActivity;

    protected $table = 'app_user';

    public $timestamps = true;

    protected $rememberTokenName = null;

    protected $fillable = [
        'name',
        'email',
        'mobile_number',
        'password_hash',
        'user_type_id',
        'cafe_id',
        'is_active',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function userType(): BelongsTo
    {
        return $this->belongsTo(UserType::class);
    }

    public function cafe(): BelongsTo
    {
        return $this->belongsTo(Cafe::class);
    }

    public function createdCafes(): HasMany
    {
        return $this->hasMany(Cafe::class, 'created_by_admin_id');
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    public function delegatedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'delegate_id');
    }

    public function orderStatusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class, 'changed_by');
    }
}
