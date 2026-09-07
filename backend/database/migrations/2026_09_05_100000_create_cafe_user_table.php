<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move the cafe link and delegate location fields off app_user into a
     * dedicated cafe_user table, making app_user a general users table.
     */
    public function up(): void
    {
        Schema::create('cafe_user', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('cafe_id')->unsigned()->nullable();
            $table->integer('user_id')->unsigned();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_available')->default(false);
            $table->timestamp('location_updated_at')->nullable();
            $table->timestamps();

            $table->foreign('cafe_id')->references('id')->on('cafe')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('app_user')->cascadeOnDelete();
            $table->unique(['cafe_id', 'user_id']);
        });

        DB::table('cafe_user')->insertUsing(
            ['cafe_id', 'user_id', 'latitude', 'longitude', 'is_available', 'location_updated_at', 'created_at', 'updated_at'],
            DB::table('app_user')
                ->select('cafe_id', 'id', 'latitude', 'longitude', 'is_available', 'location_updated_at', DB::raw('CURRENT_TIMESTAMP'), DB::raw('CURRENT_TIMESTAMP'))
                ->whereNotNull('cafe_id')
                ->orWhereNotNull('latitude')
                ->orWhereNotNull('longitude')
        );

        Schema::table('app_user', function (Blueprint $table) {
            $table->dropForeign(['cafe_id']);
            $table->dropColumn(['cafe_id', 'latitude', 'longitude', 'is_available', 'location_updated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('app_user', function (Blueprint $table) {
            $table->integer('cafe_id')->unsigned()->nullable()->after('user_type_id');
            $table->decimal('latitude', 10, 8)->nullable()->after('cafe_id');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->boolean('is_available')->default(false)->after('longitude');
            $table->timestamp('location_updated_at')->nullable()->after('is_available');

            $table->foreign('cafe_id')->references('id')->on('cafe');
        });

        $links = DB::table('cafe_user')->get();
        foreach ($links as $link) {
            DB::table('app_user')
                ->where('id', $link->user_id)
                ->update([
                    'cafe_id' => $link->cafe_id,
                    'latitude' => $link->latitude,
                    'longitude' => $link->longitude,
                    'is_available' => $link->is_available,
                    'location_updated_at' => $link->location_updated_at,
                ]);
        }

        Schema::dropIfExists('cafe_user');
    }
};
