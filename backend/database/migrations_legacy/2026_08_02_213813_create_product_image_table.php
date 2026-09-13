<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_image', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('product_id')->unsigned();
            $table->text('url');
            $table->boolean('is_primary')->default(false);

            $table->foreign('product_id')->references('id')->on('product')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_image');
    }
};
