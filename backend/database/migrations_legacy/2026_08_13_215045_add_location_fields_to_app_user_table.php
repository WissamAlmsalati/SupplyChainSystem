<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('app_user', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->after('cafe_id');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->boolean('is_available')->default(false)->after('longitude');
            $table->timestamp('location_updated_at')->nullable()->after('is_available');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_user', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'is_available', 'location_updated_at']);
        });
    }
};
