<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\LogsActivity;

class AppUser extends Authenticatable
{
    use HasApiTokens, HasFactory, LogsActivity;

    protected $table = 'user';

    public $timestamps = true;

    protected $rememberTokenName = null;

    protected $fillable = [
        'name',
        'email',
        'mobile_number',
        'password_hash',
        'user_type_id',
        'is_active',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $appends = [
        'cafe_id',
        'latitude',
        'longitude',
        'is_available',
        'location_updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    // ponytail: cafe_id / location / availability live on the cafe_user pivot
    // and are exposed as virtual attributes so API responses keep their shape.
    // Ceiling: every serialized user lazy-loads the pivot unless eager loaded.

    public function getCafeIdAttribute(): ?int
    {
        return $this->cafeUser?->cafe_id;
    }

    public function getLatitudeAttribute(): ?string
    {
        return $this->cafeUser?->latitude;
    }

    public function getLongitudeAttribute(): ?string
    {
        return $this->cafeUser?->longitude;
    }

    public function getIsAvailableAttribute(): ?bool
    {
        return $this->cafeUser?->is_available;
    }

    public function getLocationUpdatedAtAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->cafeUser?->location_updated_at;
    }

    /**
     * Update the cafe link (cafe_user pivot) from request-style data.
     * Keys: cafe_id, latitude, longitude, is_available, location_updated_at. Null values are
     * ignored; an explicit null cafe_id removes the link.
     */
    public function syncCafeUser(array $data): void
    {
        $fields = collect($data)->only(['cafe_id', 'latitude', 'longitude', 'is_available', 'location_updated_at'])->all();

        if (array_key_exists('cafe_id', $fields) && is_null($fields['cafe_id'])) {
            $this->cafeUser?->delete();
            $this->unsetRelation('cafeUser');

            return;
        }

        $update = collect($fields)->filter(fn ($v) => ! is_null($v))->all();

        if (empty($update)) {
            return;
        }

        // ponytail: updateOrCreate also creates a cafe-less link so location
        // and availability are not lost for unassigned delegates.
        $this->cafeUser()->updateOrCreate([], $update);

        $this->unsetRelation('cafeUser');
    }

    public function userType(): BelongsTo
    {
        return $this->belongsTo(UserType::class);
    }

    public function cafeUser(): HasOne
    {
        return $this->hasOne(CafeUser::class, 'user_id');
    }

    public function cafes(): BelongsToMany
    {
        return $this->belongsToMany(Cafe::class, 'cafe_user', 'user_id', 'cafe_id')
            ->withPivot(['latitude', 'longitude', 'is_available', 'location_updated_at']);
    }

    public function cafe(): HasOneThrough
    {
        return $this->hasOneThrough(Cafe::class, CafeUser::class, 'user_id', 'id', 'id', 'cafe_id');
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
