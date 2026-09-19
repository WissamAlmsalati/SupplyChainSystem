<?php

namespace App\Models;

use App\Enums\CartType;
use App\Enums\UserRole;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class AppUser extends Authenticatable
{
    use HasApiTokens, HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'users';

    protected $rememberTokenName = null;

    protected $fillable = [
        'name',
        'email',
        'mobile_number',
        'password',
        'user_type_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::created(fn (AppUser $user) => $user->ensureProfile());
        static::updated(function (AppUser $user) {
            if ($user->wasChanged('user_type_id')) {
                $user->ensureProfile();
            }
            // Switching an account off, or changing its password, must end the
            // sessions that were opened before: a stolen or lent phone stays
            // signed in otherwise. The session making the change survives.
            if ($user->wasChanged('is_active') && ! $user->is_active) {
                $user->tokens()->delete();
            } elseif ($user->wasChanged('password')) {
                // The session that is changing its own password stays signed in.
                $current = auth()->id() === $user->id ? auth()->user()?->currentAccessToken()?->id : null;
                $user->tokens()->when($current, fn ($q) => $q->where('id', '!=', $current))->delete();
            }
        });
    }

    public function roleName(): ?string
    {
        return $this->userType?->name;
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->roleName(), array_map(fn (UserRole $r) => $r->value, $roles), true);
    }

    // Creates the profile row that matches the user's type, if missing.
    public function ensureProfile(): void
    {
        $this->unsetRelation('userType');

        $relation = match ($this->roleName()) {
            UserRole::Customer->value => 'customerProfile',
            UserRole::Delegate->value => 'delegateProfile',
            UserRole::Admin->value, UserRole::SuperAdmin->value => 'adminProfile',
            default => null,
        };

        if ($relation && ! $this->{$relation}()->exists()) {
            $this->{$relation}()->create();
            $this->unsetRelation($relation);
        }

        if ($relation === 'customerProfile' && ! $this->wallet()->exists()) {
            $this->wallet()->create();
            $this->unsetRelation('wallet');
        }
    }

    public function userType(): BelongsTo
    {
        return $this->belongsTo(UserType::class);
    }

    public function adminProfile(): HasOne
    {
        return $this->hasOne(AdminProfile::class, 'user_id');
    }

    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class, 'user_id');
    }

    public function delegateProfile(): HasOne
    {
        return $this->hasOne(DelegateProfile::class, 'user_id');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'user_id');
    }

    // Newest favorite first.
    public function favoriteProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'favorites', 'user_id', 'product_id')
            ->withPivot('created_at')
            ->orderByPivot('created_at', 'desc')
            ->orderByPivot('id', 'desc');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'user_id');
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class, 'user_id');
    }

    public function shoppingCart(): HasOne
    {
        return $this->hasOne(Cart::class, 'user_id')->where('type', CartType::Shopping->value);
    }

    public function recurringCarts(): HasMany
    {
        return $this->hasMany(Cart::class, 'user_id')->where('type', CartType::Recurring->value);
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
