<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Curated product rows shown in the customer app (e.g. "الأكثر طلباً").
        Schema::create('featured_sections', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('featured_section_product', function (Blueprint $table) {
            $table->foreignId('featured_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->primary(['featured_section_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_section_product');
        Schema::dropIfExists('featured_sections');
    }
};
