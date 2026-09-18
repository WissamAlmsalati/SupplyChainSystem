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
        return $this->belongsTo(AppUser::class);
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
        self::sendTo(
            AppUser::whereHas('userType', fn ($q) => $q->whereIn('name', ['admin', 'super_admin']))->pluck('id'),
            $title, $message, $link, $type,
        );
    }

    /**
     * One notification row per recipient, inserted in a single statement.
     * The batch shares one created_at, which is how "sent" history groups it.
     *
     * @param  iterable<int>  $userIds
     */
    public static function sendTo(iterable $userIds, string $title, ?string $message = null, ?string $link = null, string $type = 'info'): int
    {
        $now = now();
        $records = collect($userIds)->unique()->values()->map(fn ($userId) => [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        foreach (array_chunk($records, 500) as $chunk) {
            self::insert($chunk);
        }

        return count($records);
    }
}
