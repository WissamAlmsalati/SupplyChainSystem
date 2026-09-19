<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A cash refund is owed from the moment the return is recorded, but it is only
// paid when somebody hands the money over. Who did, when, and out of whose
// cash, was not written down anywhere.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_returns', 'refund_paid_at')) {
            Schema::table('order_returns', function (Blueprint $table) {
                $table->timestamp('refund_paid_at')->nullable()->after('refund_method');
                $table->foreignId('refund_paid_by')->nullable()->after('refund_paid_at')->constrained('users')->nullOnDelete();
                // Set when a delegate paid it out of the cash they hold.
                $table->foreignId('refund_paid_from_delegate_id')->nullable()->after('refund_paid_by')->constrained('users')->nullOnDelete();
            });
        }

        // Wallet refunds were paid the moment they were recorded.
        DB::table('order_returns')->where('refund_method', 'wallet')->whereNull('refund_paid_at')
            ->update(['refund_paid_at' => DB::raw('created_at'), 'refund_paid_by' => DB::raw('created_by')]);
    }

    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refund_paid_from_delegate_id');
            $table->dropConstrainedForeignId('refund_paid_by');
            $table->dropColumn('refund_paid_at');
        });
    }
};
