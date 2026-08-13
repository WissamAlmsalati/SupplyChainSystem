<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zone', function (Blueprint $table) {
            $table->increments('id');
            $table->string('hex_id', 30)->unique();
            $table->string('name', 100)->nullable();
            $table->decimal('delivery_price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zone');
    }
};
