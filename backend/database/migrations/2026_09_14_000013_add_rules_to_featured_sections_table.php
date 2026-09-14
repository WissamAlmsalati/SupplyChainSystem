<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('featured_sections', function (Blueprint $table) {
            // manual: hand-picked products in featured_section_product.
            // filter: products computed at request time from `filters` + `sort` (e.g. best sellers).
            $table->string('source', 20)->default('manual')->after('title');
            $table->string('sort', 20)->nullable()->after('source');
            $table->json('filters')->nullable()->after('sort');
            // How many products the home screen shows before "view all".
            $table->unsignedTinyInteger('products_limit')->default(10)->after('filters');
        });
    }

    public function down(): void
    {
        Schema::table('featured_sections', function (Blueprint $table) {
            $table->dropColumn(['source', 'sort', 'filters', 'products_limit']);
        });
    }
};
