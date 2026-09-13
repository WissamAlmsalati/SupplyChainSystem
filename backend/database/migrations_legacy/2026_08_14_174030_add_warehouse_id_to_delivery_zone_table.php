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
        Schema::table('delivery_zone', function (Blueprint $table) {
            $table->unsignedInteger('warehouse_id')->nullable()->after('id');
            $table->foreign('warehouse_id')->references('id')->on('warehouse')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_zone', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }
};
