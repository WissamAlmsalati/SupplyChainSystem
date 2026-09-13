<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('app_user', 'user');
    }

    public function down(): void
    {
        Schema::rename('user', 'app_user');
    }
};
