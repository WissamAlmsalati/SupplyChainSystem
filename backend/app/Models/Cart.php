<?php

namespace App\Models;

use App\Enums\CartType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'name',
    ];

    protected $casts = [
        'type' => CartType::class,
    ];

    protected $hidden = [
        'shopping_owner_id',
    ];

    public function scopeShopping(Builder $query): void
    {
        $query->where('type', CartType::Shopping->value);
    }

    public function scopeRecurring(Builder $query): void
    {
        $query->where('type', CartType::Recurring->value);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // Sum of items at current variant prices.
    public function subtotal(): float
    {
        $this->loadMissing('items.productVariant');

        return round($this->items->sum(fn (CartItem $item) => $item->quantity * (float) $item->productVariant?->price), 2);
    }
}
