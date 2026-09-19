<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // What the cafe wants the office and the driver to know.
            if (! Schema::hasColumn('orders', 'customer_note')) {
                $table->string('customer_note', 500)->nullable()->after('source');
            }
            // Why the last delivery attempt failed, and how many were made.
            if (! Schema::hasColumn('orders', 'delivery_failure_reason')) {
                $table->string('delivery_failure_reason', 30)->nullable()->after('customer_note');
                $table->string('delivery_failure_note', 255)->nullable()->after('delivery_failure_reason');
                $table->unsignedSmallInteger('delivery_attempts')->default(0)->after('delivery_failure_note');
            }
        });

        // A web path like /orders/52 means nothing to a Flutter app; this does.
        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'entity_type')) {
                $table->string('entity_type', 30)->nullable()->after('link');
                $table->unsignedBigInteger('entity_id')->nullable()->after('entity_type');
            }
        });

        // Where to push a notification to: one row per installed app.
        if (! Schema::hasTable('device_tokens')) {
            Schema::create('device_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                // FCM registration tokens are long and belong to one install.
                $table->string('token', 512)->unique();
                $table->string('platform', 10);
                $table->string('app', 10);
                $table->string('device_name', 100)->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'app']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
        Schema::table('notifications', fn (Blueprint $table) => $table->dropColumn(['entity_type', 'entity_id']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['customer_note', 'delivery_failure_reason', 'delivery_failure_note', 'delivery_attempts']));
    }
};
