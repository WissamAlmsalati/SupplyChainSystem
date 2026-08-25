<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_log', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('order_id')->unsigned();
            $table->string('status', 30);
            $table->integer('changed_by')->unsigned()->nullable();
            $table->timestamp('changed_at')->default(now());

            $table->foreign('order_id')->references('id')->on('order')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('app_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_log');
    }
};
