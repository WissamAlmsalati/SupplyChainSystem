<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->timestamps();
        });

        Schema::create('user_type_permission', function (Blueprint $table) {
            $table->foreignId('user_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_type_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_type_permission');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('user_types');
    }
};
