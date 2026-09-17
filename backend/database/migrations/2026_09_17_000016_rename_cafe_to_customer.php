<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The customer app was called "cafe" in code and data. Code now says
// "customer" everywhere; this renames the stored values to match, so roles,
// permission codes and feature flags keep working after deploy.
return new class extends Migration
{
    private const USER_TYPES = ['cafe' => 'customer'];

    private const PERMISSIONS = [
        'CAFE_BRANCHES_VIEW' => 'CUSTOMER_BRANCHES_VIEW',
        'CAFE_BRANCHES_CREATE' => 'CUSTOMER_BRANCHES_CREATE',
        'CAFE_BRANCHES_EDIT' => 'CUSTOMER_BRANCHES_EDIT',
        'CAFE_BRANCHES_DELETE' => 'CUSTOMER_BRANCHES_DELETE',
        'CAFE_REGISTRATIONS_VIEW' => 'CUSTOMER_REGISTRATIONS_VIEW',
        'CAFE_REGISTRATIONS_APPROVE' => 'CUSTOMER_REGISTRATIONS_APPROVE',
    ];

    private const PREMIUM_FEATURES = [
        'cafe_auto_approve' => 'customer_auto_approve',
        'cafe_branches' => 'customer_branches',
    ];

    private const NOTIFICATION_TYPES = ['cafe_registration' => 'customer_registration'];

    public function up(): void
    {
        $this->rename('user_types', 'name', self::USER_TYPES);
        $this->rename('permissions', 'code', self::PERMISSIONS);
        $this->rename('premium_features', 'code', self::PREMIUM_FEATURES);
        $this->rename('notifications', 'type', self::NOTIFICATION_TYPES);
    }

    public function down(): void
    {
        $this->rename('user_types', 'name', array_flip(self::USER_TYPES));
        $this->rename('permissions', 'code', array_flip(self::PERMISSIONS));
        $this->rename('premium_features', 'code', array_flip(self::PREMIUM_FEATURES));
        $this->rename('notifications', 'type', array_flip(self::NOTIFICATION_TYPES));
    }

    private function rename(string $table, string $column, array $map): void
    {
        foreach ($map as $from => $to) {
            DB::table($table)->where($column, $from)->update([$column => $to]);
        }
    }
};
