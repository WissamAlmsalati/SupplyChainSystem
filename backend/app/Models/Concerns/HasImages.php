<?php

namespace App\Models\Concerns;

use App\Models\Image;
use App\Support\Placeholder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Pictures for any record, always answered as a list.
 *
 * A record with no pictures of its own answers one entry carrying the default
 * artwork, with a null id. That keeps the old promise that a picture URL is
 * never missing, and saves every client a branch for the empty case; a null id
 * is also how a client knows there is nothing there to delete.
 *
 * A model using this trait adds 'images' to $appends and names its placeholder
 * kind in $placeholderKind.
 */
trait HasImages
{
    public function images(): MorphMany
    {
        // The primary leads, then the order the owner chose, then age. Clients
        // are told images[0] is the one to show, so the server must mean it.
        return $this->morphMany(Image::class, 'imageable')
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function getImagesAttribute(): array
    {
        $images = $this->relationLoaded('images')
            ? $this->getRelation('images')
            : $this->images()->get();

        if ($images->isEmpty()) {
            return [$this->placeholderImage()];
        }

        return $images->map(fn (Image $image) => $image->toClient())->values()->all();
    }

    /** The default artwork, dressed as a picture so the list is never empty. */
    protected function placeholderImage(): array
    {
        $url = Placeholder::url($this->placeholderKind ?? 'product');

        return [
            'id' => null,
            'url' => $url,
            'type' => Placeholder::typeFor($url),
            'is_primary' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Stores an uploaded file and attaches it. The first picture a record gets
     * is its primary, so a record always has one to show without anybody
     * choosing; a later one only takes over when asked.
     */
    public function attachImage(UploadedFile $file, string $directory, bool $primary = false): Image
    {
        return DB::transaction(function () use ($file, $directory, $primary) {
            $existing = $this->images()->count();
            $image = $this->images()->create([
                'path' => $file->store($directory, 'public'),
                'is_primary' => $primary || $existing === 0,
                'sort_order' => (int) $this->images()->max('sort_order') + ($existing ? 1 : 0),
            ]);

            if ($image->is_primary) {
                $this->demoteOtherPrimaries($image);
            }

            return $image;
        });
    }

    /** Exactly one picture is primary; promoting one demotes the rest. */
    public function makePrimary(Image $image): void
    {
        DB::transaction(function () use ($image) {
            $image->update(['is_primary' => true]);
            $this->demoteOtherPrimaries($image);
        });
    }

    private function demoteOtherPrimaries(Image $image): void
    {
        $this->images()->whereKeyNot($image->getKey())->where('is_primary', true)->update(['is_primary' => false]);
    }
}
