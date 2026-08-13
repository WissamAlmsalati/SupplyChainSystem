<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->integer('parent_category_id')->unsigned()->nullable();
            $table->foreign('parent_category_id')->references('id')->on('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category');
    }
};
