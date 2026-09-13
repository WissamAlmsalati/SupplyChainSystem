<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('category_id')->unsigned();
            $table->integer('supplier_id')->unsigned()->nullable();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->timestamp('created_at')->default(now());

            $table->foreign('category_id')->references('id')->on('category');
            $table->foreign('supplier_id')->references('id')->on('supplier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product');
    }
};
