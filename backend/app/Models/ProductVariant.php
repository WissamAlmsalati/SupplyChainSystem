<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory, \App\Traits\LogsActivity;

    protected $table = 'product_variant';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'sku',
        'attribute_name',
        'attribute_value',
        'price',
        'sell_price',
        'cost_price',
        'manufacturing_year',
        'expiry_date',
        'barcode',
        'stock_quantity',
        'is_active',
        'status',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'manufacturing_year' => 'integer',
        'expiry_date' => 'date',
        'stock_quantity' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
