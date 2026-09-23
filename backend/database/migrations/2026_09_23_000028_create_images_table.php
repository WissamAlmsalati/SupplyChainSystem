<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One home for every picture in the system, so "anything with pictures answers
// a list" is a fact in one place rather than a convention each model has to
// remember. product_images stays where it is for now; it moves here next.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->morphs('imageable');
            // Long enough for an external URL, as product_images already allows.
            $table->string('path', 500);
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['imageable_type', 'imageable_id', 'sort_order'], 'images_owner_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
