<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The warehouse an order is picked from. Stock used to be taken from whichever
// warehouse had the lowest id, so a Benghazi order emptied the Tripoli shelf in
// the books while the goods left Benghazi, and nobody was told where to load.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'warehouse_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('warehouse_id')->nullable()->after('delivery_zone_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
