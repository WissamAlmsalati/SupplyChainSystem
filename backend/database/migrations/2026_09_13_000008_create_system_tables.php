<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50)->default('info');
            $table->string('title', 150);
            $table->text('message')->nullable();
            $table->string('link', 255)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name', 100)->nullable();
            $table->string('action', 50);
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
        });

        // Holds password-reset OTPs and pending registrations (payload set).
        Schema::create('password_reset_otps', function (Blueprint $table) {
            $table->id();
            $table->string('mobile_number', 20)->index();
            $table->string('token', 64)->unique();
            $table->string('otp', 10);
            $table->json('payload')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('premium_features', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->string('link')->nullable();
            $table->boolean('show_description')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promos');
        Schema::dropIfExists('premium_features');
        Schema::dropIfExists('password_reset_otps');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('notifications');
    }
};
