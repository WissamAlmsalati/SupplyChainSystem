<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    /** Never written to the log, whatever model they are on. */
    private static array $neverLogged = ['password', 'remember_token', 'otp', 'token'];

    public static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            self::logActivity($model, 'created', 'تم الإنشاء');
        });

        static::updated(function ($model) {
            self::logActivity($model, 'updated', 'تم التعديل');
        });

        static::deleted(function ($model) {
            self::logActivity($model, 'deleted', 'تم الحذف');
        });
    }

    protected static function logActivity($model, string $action, string $actionLabel): void
    {
        $user = Auth::user();
        $entityType = class_basename($model);
        $modelName = $model->name
            ?? $model->title
            ?? $model->email
            ?? $model->code
            ?? null;

        $description = match ($action) {
            'created' => "{$actionLabel}: {$entityType}".($modelName ? " ({$modelName})" : ''),
            'updated' => "{$actionLabel}: {$entityType}".($modelName ? " ({$modelName})" : ''),
            'deleted' => "{$actionLabel}: {$entityType}".($modelName ? " ({$modelName})" : ''),
            default => "{$actionLabel}: {$entityType}",
        };

        // getChanges() ignores $hidden, so secrets are dropped by name: a log
        // page is read by many people and is no place for a password hash.
        $changes = array_diff_key($model->getChanges(), array_flip(self::$neverLogged));
        if ($action === 'updated' && array_diff_key($changes, ['updated_at' => 0]) === []) {
            $changes = $model->wasChanged('password') ? ['password' => '••••••'] : $changes;
        }

        ActivityLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'نظام',
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $model->getKey(),
            'description' => $description,
            'metadata' => [
                'name' => $modelName,
                'changes' => $changes,
                // What it was before, so the log answers "changed from what?".
                'before' => array_intersect_key($model->getOriginal(), $changes),
            ],
        ]);
    }
}
