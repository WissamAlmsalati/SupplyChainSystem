<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'warehouse_id',
        'product_variant_id',
        'quantity_change',
        'type',
        'unit_cost',
        'manufacturing_year',
        'expiry_date',
        'reference_type',
        'reference_id',
        'note',
        'created_by',
    ];

    protected $casts = [
        'quantity_change' => 'integer',
        'type' => StockMovementType::class,
        'unit_cost' => 'decimal:2',
        'manufacturing_year' => 'integer',
        'expiry_date' => 'date:Y-m-d',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function productVariant(): BelongsTo
    {
        // History keeps its labels after a size leaves the catalogue.
        return $this->belongsTo(ProductVariant::class)->withTrashed();
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'created_by');
    }
}
