<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->unsignedInteger('warehouse_id')->nullable()->change();
            $table->dropForeign(['warehouse_id']);
            $table->foreign('warehouse_id')->references('id')->on('warehouse')->onDelete('set null');
        });

        Schema::table('purchase_order', function (Blueprint $table) {
            $table->unsignedInteger('warehouse_id')->nullable()->change();
            $table->dropForeign(['warehouse_id']);
            $table->foreign('warehouse_id')->references('id')->on('warehouse')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->foreign('warehouse_id')->references('id')->on('warehouse');
            $table->unsignedInteger('warehouse_id')->nullable(false)->change();
        });

        Schema::table('purchase_order', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->foreign('warehouse_id')->references('id')->on('warehouse');
            $table->unsignedInteger('warehouse_id')->nullable(false)->change();
        });
    }
};
