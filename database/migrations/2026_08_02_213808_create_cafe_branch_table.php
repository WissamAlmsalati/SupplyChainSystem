<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cafe_branch', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('cafe_id')->unsigned();
            $table->string('name', 100);
            $table->string('city', 100)->nullable();
            $table->string('street', 200)->nullable();
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
            $table->integer('delivery_zone_id')->unsigned()->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->default(now());

            $table->foreign('cafe_id')->references('id')->on('cafe')->onDelete('cascade');
            $table->foreign('delivery_zone_id')->references('id')->on('delivery_zone');

            $table->index('cafe_id', 'idx_cafe_branch_cafe');
            $table->index('delivery_zone_id', 'idx_cafe_branch_zone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cafe_branch');
    }
};
