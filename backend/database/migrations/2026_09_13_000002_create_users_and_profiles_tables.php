<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 150)->nullable()->unique();
            $table->string('mobile_number', 20)->nullable()->unique();
            $table->string('password');
            $table->foreignId('user_type_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // One profile table per user type; a user has exactly one type.
        Schema::create('admin_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_name', 150)->nullable();
            // Registered location, used for delivery pricing when the customer has no address.
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();
        });

        Schema::create('delegate_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_available')->default(false);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamp('location_updated_at')->nullable();
            $table->timestamps();

            $table->index(['is_available', 'location_updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegate_profiles');
        Schema::dropIfExists('customer_profiles');
        Schema::dropIfExists('admin_profiles');
        Schema::dropIfExists('users');
    }
};
