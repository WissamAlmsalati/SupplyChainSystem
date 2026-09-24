<?php

namespace App\Models;

use App\Support\Placeholder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promo extends Model
{
    use HasFactory;

    protected $fillable = ['image', 'description', 'link', 'deeplink_entity', 'deeplink_entity_id', 'show_description', 'is_active'];

    protected $casts = [
        'show_description' => 'boolean',
        'is_active' => 'boolean',
        'deeplink_entity_id' => 'integer',
    ];

    // A banner is artwork, not a record anybody audits; the apps that draw it
    // have no use for when it was edited.
    protected $hidden = ['created_at', 'updated_at'];

    protected $appends = ['image_url', 'image_type', 'image_is_placeholder'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? '/storage/'.ltrim($this->image, '/') : Placeholder::url('promo');
    }

    public function getImageTypeAttribute(): ?string
    {
        return Placeholder::typeFor($this->image_url);
    }

    // True while the picture is the shared default artwork, not one somebody uploaded.
    public function getImageIsPlaceholderAttribute(): bool
    {
        return Placeholder::isPlaceholder($this->image_url);
    }

    /**
     * What the apps are given. Built field by field on purpose: every banner
     * carries exactly these keys, in this order, whatever it points at — a
     * client should never have to check whether a key is there.
     *
     * `image` is the URL to draw, not the storage path behind it; there is one
     * picture field, so there is nothing to choose between.
     */
    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'image' => $this->image_url,
            'image_type' => $this->image_type,
            'image_is_placeholder' => $this->image_is_placeholder,
            'description' => $this->description,
            'show_description' => (bool) $this->show_description,
            'is_active' => (bool) $this->is_active,
            // Out of the platform. Null whenever the banner points inside it.
            'link' => $this->link,
            // Inside the apps: the destination by name, and the id it needs.
            'deeplink_entity' => $this->deeplink_entity,
            'deeplink_entity_id' => $this->deeplink_entity_id,
        ];
    }
}
