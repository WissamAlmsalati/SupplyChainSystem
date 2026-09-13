<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cash a delegate is holding for the office (عهدة). Cached sum of custody_entries.
        Schema::table('delegate_profiles', function (Blueprint $table) {
            $table->decimal('custody_balance', 12, 2)->default(0)->after('location_updated_at');
        });

        // Who physically received a cash payment.
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('collected_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
        });

        // Handing collected cash over to the office (تسكير الحساب).
        Schema::create('delegate_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 30)->unique();
            $table->foreignId('delegate_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('custody_before', 12, 2);
            $table->decimal('custody_after', 12, 2);
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Append-only custody ledger; amount is signed (+ cash received, - handed over).
        Schema::create('custody_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegate_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 30);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->nullableMorphs('reference');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['delegate_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custody_entries');
        Schema::dropIfExists('delegate_settlements');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collected_by');
        });
        Schema::table('delegate_profiles', function (Blueprint $table) {
            $table->dropColumn('custody_balance');
        });
    }
};
