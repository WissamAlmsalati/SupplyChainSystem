<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cafe', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 150);
            $table->string('contact_info', 200)->nullable();
            $table->integer('created_by_admin_id')->unsigned()->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->default(now());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cafe');
    }
};
