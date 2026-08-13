<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('branch_id')->unsigned();
            $table->string('status', 20)->default('active');
            $table->timestamp('created_at')->default(now());

            $table->foreign('user_id')->references('id')->on('app_user')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('cafe_branch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart');
    }
};
