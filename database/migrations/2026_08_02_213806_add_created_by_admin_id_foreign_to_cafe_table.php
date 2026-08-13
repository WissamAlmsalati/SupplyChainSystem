<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cafe', function (Blueprint $table) {
            $table->foreign('created_by_admin_id')->references('id')->on('app_user');
        });
    }

    public function down(): void
    {
        Schema::table('cafe', function (Blueprint $table) {
            $table->dropForeign(['created_by_admin_id']);
        });
    }
};
