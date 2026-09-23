<?php

namespace App\Models;

use App\Models\Concerns\HasImages;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProfile extends Model
{
    // Renaming the cafe is a change the office should be able to see, with the
    // name it had before; the trait records both.
    use HasImages, LogsActivity;

    // Pictures of the cafe itself — the shop, the counter — as opposed to the
    // pictures of a branch's door that hang on an Address.
    protected string $placeholderKind = 'customer';

    protected $appends = ['images'];

    protected $fillable = [
        'user_id',
        'business_name',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id');
    }
}
