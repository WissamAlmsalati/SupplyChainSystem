<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The system has no default or inactive address: a cafe's branches are just
// its addresses, and removing one is a (soft) delete.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['is_default', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('delivery_zone_id');
            $table->boolean('is_active')->default(true)->after('is_default');
        });
    }
};
