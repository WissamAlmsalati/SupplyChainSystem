<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('branch_id')->unsigned();
            $table->integer('delegate_id')->unsigned()->nullable();
            $table->integer('delivery_zone_id')->unsigned()->nullable();
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->timestamp('order_date')->default(now());
            $table->string('status', 30)->default('pending');
            $table->decimal('total_amount', 10, 2);

            $table->foreign('user_id')->references('id')->on('app_user');
            $table->foreign('branch_id')->references('id')->on('cafe_branch');
            $table->foreign('delegate_id')->references('id')->on('app_user');
            $table->foreign('delivery_zone_id')->references('id')->on('delivery_zone');

            $table->index('user_id', 'idx_order_user');
            $table->index('delegate_id', 'idx_order_delegate');
            $table->index('branch_id', 'idx_order_branch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order');
    }
};
