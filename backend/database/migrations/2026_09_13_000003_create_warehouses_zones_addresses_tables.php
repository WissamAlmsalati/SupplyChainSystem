<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('city', 100)->nullable();
            $table->string('hex_id', 40)->nullable()->unique();
            $table->unsignedTinyInteger('resolution')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hex_id', 40)->unique();
            $table->string('name', 100)->nullable();
            $table->decimal('delivery_price', 10, 2);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('city', 100)->nullable();
            $table->string('street', 200)->nullable();
            $table->json('contact_phones')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('warehouses');
    }
};
