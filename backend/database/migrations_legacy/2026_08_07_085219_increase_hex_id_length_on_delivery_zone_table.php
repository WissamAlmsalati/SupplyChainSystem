<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_zone', function (Blueprint $table) {
            $table->string('hex_id', 40)->change();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_zone', function (Blueprint $table) {
            $table->string('hex_id', 30)->change();
        });
    }
};
