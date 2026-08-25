<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_user', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('email', 150)->unique();
            $table->string('mobile_number', 20)->unique()->nullable();
            $table->text('password_hash');
            $table->integer('user_type_id')->unsigned();
            $table->integer('cafe_id')->unsigned()->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('user_type_id')->references('id')->on('user_type');
            $table->foreign('cafe_id')->references('id')->on('cafe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_user');
    }
};
