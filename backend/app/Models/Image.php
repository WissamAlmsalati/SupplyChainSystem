<?php

namespace App\Models;

use App\Support\Placeholder;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// A picture belonging to any record. What a client is given is assembled by
// HasImages; this is the row and the file behind it.
class Image extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['imageable_type', 'imageable_id', 'path', 'is_primary', 'sort_order'];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    // Whether the picture lives on the public disk, rather than being a URL
    // somebody pasted. Only a stored file is ours to delete.
    public function isStored(): bool
    {
        return ! Str::startsWith($this->path, ['http://', 'https://', '/']);
    }

    public function url(): string
    {
        return $this->isStored() ? '/storage/'.$this->path : $this->path;
    }

    // Deleting the row deletes the file with it: a picture nothing points to is
    // just bytes the office pays to keep.
    protected static function booted(): void
    {
        static::deleted(function (Image $image) {
            if ($image->isStored()) {
                Storage::disk('public')->delete($image->path);
            }
        });
    }

    /** The shape every client sees, wherever the picture hangs. */
    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(),
            'type' => Placeholder::typeFor($this->path),
            'is_primary' => (bool) $this->is_primary,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
