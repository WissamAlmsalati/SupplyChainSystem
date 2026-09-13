<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_otps', function (Blueprint $table) {
            $table->id();
            $table->string('mobile_number', 20);
            $table->string('token', 64)->unique();
            $table->string('otp', 10);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('mobile_number');
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_otps');
    }
};
