<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_type_permission', function (Blueprint $table) {
            $table->integer('user_type_id')->unsigned();
            $table->integer('permission_id')->unsigned();
            $table->primary(['user_type_id', 'permission_id']);
            $table->foreign('user_type_id')->references('id')->on('user_type')->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on('permission')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_type_permission');
    }
};
