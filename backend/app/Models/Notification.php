<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'link',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    public static function notifyAdmins(string $title, ?string $message = null, ?string $link = null, string $type = 'info'): void
    {
        $admins = User::whereHas('userType', fn ($q) => $q->whereIn('name', ['admin', 'super_admin']))
            ->pluck('id');

        $records = $admins->map(fn ($userId) => [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if ($records) {
            self::insert($records);
        }
    }
}
