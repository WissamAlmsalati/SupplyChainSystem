<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One phone number, one person — but the same person can be a customer of the
 * shop and drive for it, and those are two accounts under this schema (a user
 * has exactly one type). A number unique across the whole table made that
 * impossible, so uniqueness moves to (number, type): a number may appear once
 * per type and no more. Email stays globally unique; staff sign in with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $clashes = DB::table('users')
            ->selectRaw('mobile_number, user_type_id, COUNT(*) as total')
            ->whereNotNull('mobile_number')
            ->groupBy('mobile_number', 'user_type_id')
            ->having('total', '>', 1)
            ->count();

        if ($clashes > 0) {
            throw new RuntimeException("Refusing to migrate: {$clashes} phone numbers are already duplicated within one user type.");
        }

        // A fresh install already builds the composite index, so only swap
        // where the old global one is still in place.
        $indexes = collect(Schema::getIndexes('users'))->pluck('name');

        Schema::table('users', function (Blueprint $table) use ($indexes) {
            if ($indexes->contains('users_mobile_number_unique')) {
                $table->dropUnique('users_mobile_number_unique');
            }
            if (! $indexes->contains('users_mobile_number_user_type_unique')) {
                $table->unique(['mobile_number', 'user_type_id'], 'users_mobile_number_user_type_unique');
            }
        });
    }

    public function down(): void
    {
        $clashes = DB::table('users')
            ->selectRaw('mobile_number, COUNT(*) as total')
            ->whereNotNull('mobile_number')
            ->groupBy('mobile_number')
            ->having('total', '>', 1)
            ->count();

        if ($clashes > 0) {
            throw new RuntimeException("Cannot restore the global unique index: {$clashes} numbers are shared between user types.");
        }

        $indexes = collect(Schema::getIndexes('users'))->pluck('name');

        Schema::table('users', function (Blueprint $table) use ($indexes) {
            if ($indexes->contains('users_mobile_number_user_type_unique')) {
                $table->dropUnique('users_mobile_number_user_type_unique');
            }
            if (! $indexes->contains('users_mobile_number_unique')) {
                $table->unique('mobile_number', 'users_mobile_number_unique');
            }
        });
    }
};
