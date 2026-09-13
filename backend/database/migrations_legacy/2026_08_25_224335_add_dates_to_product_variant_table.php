<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variant', function (Blueprint $table) {
            $table->smallInteger('manufacturing_year')->unsigned()->nullable()->after('price');
            $table->date('expiry_date')->nullable()->after('manufacturing_year');
        });
    }

    public function down(): void
    {
        Schema::table('product_variant', function (Blueprint $table) {
            $table->dropColumn(['manufacturing_year', 'expiry_date']);
        });
    }
};
