<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The platform works with a single supplier, so supplier tracking is
     * dropped entirely: FK columns first, then the supplier table.
     */
    public function up(): void
    {
        Schema::table('product', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });

        Schema::table('purchase_order', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });

        Schema::dropIfExists('supplier');
    }

    public function down(): void
    {
        Schema::create('supplier', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 150);
            $table->string('contact_info', 200)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('product', function (Blueprint $table) {
            $table->integer('supplier_id')->unsigned()->nullable()->after('category_id');
            $table->foreign('supplier_id')->references('id')->on('supplier');
        });

        Schema::table('purchase_order', function (Blueprint $table) {
            $table->integer('supplier_id')->unsigned()->after('id');
            $table->foreign('supplier_id')->references('id')->on('supplier');
        });
    }
};
