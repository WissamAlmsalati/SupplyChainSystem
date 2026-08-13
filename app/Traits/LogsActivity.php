<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
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
            'created' => "{$actionLabel}: {$entityType}" . ($modelName ? " ({$modelName})" : ''),
            'updated' => "{$actionLabel}: {$entityType}" . ($modelName ? " ({$modelName})" : ''),
            'deleted' => "{$actionLabel}: {$entityType}" . ($modelName ? " ({$modelName})" : ''),
            default => "{$actionLabel}: {$entityType}",
        };

        ActivityLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'نظام',
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $model->getKey(),
            'description' => $description,
            'metadata' => [
                'name' => $modelName,
                'changes' => $model->getChanges(),
            ],
        ]);
    }
}
