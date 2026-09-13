<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Structural refactor: the cafe entity is removed.
 *
 * - Location/availability fields move from the cafe_user pivot onto the user
 *   table directly (values backfilled from the pivot).
 * - cafe_branch is renamed to address, gains user_id (owner) and
 *   contact_phones; cafe_id is dropped after the owner backfill.
 * - order.branch_id / cart.branch_id become address_id (FK to address).
 * - cafe and cafe_user tables are dropped.
 *
 * ponytail: sqlite cannot drop foreign keys or FK-bearing columns, so the
 * sqlite path rebuilds the affected tables instead of altering them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        // 1. Location columns on user. ponytail: longitude is decimal(11,8)
        // (same as the old cafe_user pivot) — decimal(10,8) cannot store
        // longitudes >= 100.
        Schema::table('user', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->after('is_active');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->boolean('is_available')->default(false)->after('longitude');
            $table->timestamp('location_updated_at')->nullable()->after('is_available');
        });

        // 2. Backfill user location from the pivot (latest pivot row per user wins).
        DB::table('cafe_user')->orderByDesc('id')->get()
            ->unique('user_id')
            ->each(fn ($row) => DB::table('user')->where('id', $row->user_id)->update([
                'latitude' => $row->latitude,
                'longitude' => $row->longitude,
                'is_available' => $row->is_available,
                'location_updated_at' => $row->location_updated_at,
            ]));

        // 3. cafe_branch -> address, plus owner and contact phones.
        if ($sqlite) {
            Schema::create('address', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cafe_id')->nullable(); // backfill aid, dropped in step 5
                $table->unsignedInteger('user_id')->nullable();
                $table->string('name', 100);
                $table->string('city', 100)->nullable();
                $table->string('street', 200)->nullable();
                $table->json('contact_phones')->nullable();
                $table->decimal('latitude', 9, 6);
                $table->decimal('longitude', 9, 6);
                $table->unsignedInteger('delivery_zone_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->nullable();

                $table->index('user_id', 'idx_address_user');
            });

            DB::statement('INSERT INTO address (id, cafe_id, user_id, name, city, street, latitude, longitude, delivery_zone_id, is_active, created_at)'
                . ' SELECT id, cafe_id, NULL, name, city, street, latitude, longitude, delivery_zone_id, is_active, created_at FROM cafe_branch');

            Schema::drop('cafe_branch');
        } else {
            Schema::rename('cafe_branch', 'address');

            Schema::table('address', function (Blueprint $table) {
                $table->unsignedInteger('user_id')->nullable()->after('id');
                $table->json('contact_phones')->nullable()->after('street');
                $table->index('user_id', 'idx_address_user');
            });
        }

        // 4. Owner backfill: branch.cafe_id -> cafe_user(cafe_id) -> user_id;
        // branches whose cafe has no linked user go to the first cafe user.
        $fallbackUserId = DB::table('user')
            ->join('user_type', 'user.user_type_id', '=', 'user_type.id')
            ->where('user_type.name', 'cafe')
            ->orderBy('user.id')
            ->value('user.id');

        DB::table('address')->orderBy('id')->get()->each(function ($row) use ($fallbackUserId) {
            $userId = DB::table('cafe_user')->where('cafe_id', $row->cafe_id)->orderBy('id')->value('user_id')
                ?? $fallbackUserId;

            DB::table('address')->where('id', $row->id)->update(['user_id' => $userId]);
        });

        Schema::table('address', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('user');
        });

        // 5. Drop cafe_id. The FK/index keep their original names after the
        // rename, unless down() recreated them (then it is address_cafe_id_foreign),
        // so resolve the actual constraint name first.
        if (! $sqlite) {
            $cafeFk = DB::selectOne(
                'SELECT CONSTRAINT_NAME AS name FROM information_schema.KEY_COLUMN_USAGE'
                . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME = ?',
                ['address', 'cafe_id', 'cafe']
            );

            Schema::table('address', function (Blueprint $table) use ($cafeFk) {
                if ($cafeFk) {
                    $table->dropForeign($cafeFk->name);
                }
                $table->dropIndex('idx_cafe_branch_cafe');
                $table->dropColumn('cafe_id');
            });
        } else {
            Schema::table('address', function (Blueprint $table) {
                $table->dropColumn('cafe_id');
            });
        }

        // 6. order.branch_id -> address_id.
        if ($sqlite) {
            // ponytail: sqlite index names are database-global, so the
            // secondary indexes are added after the old table is dropped.
            Schema::create('order__new', function (Blueprint $table) {
                $table->increments('id');
                $table->string('order_number', 50)->nullable();
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('address_id');
                $table->unsignedInteger('delegate_id')->nullable();
                $table->unsignedInteger('delivery_zone_id')->nullable();
                $table->decimal('delivery_fee', 10, 2)->default(0);
                $table->timestamp('order_date');
                $table->string('status', 30)->default('pending');
                $table->string('source', 30)->nullable();
                $table->decimal('total_amount', 10, 2);
            });

            DB::statement('INSERT INTO "order__new" (id, order_number, user_id, address_id, delegate_id, delivery_zone_id, delivery_fee, order_date, status, source, total_amount)'
                . ' SELECT id, order_number, user_id, branch_id, delegate_id, delivery_zone_id, delivery_fee, order_date, status, source, total_amount FROM "order"');

            Schema::drop('order');
            Schema::rename('order__new', 'order');

            Schema::table('order', function (Blueprint $table) {
                $table->unique('order_number', 'order_order_number_unique');
                $table->index('user_id', 'idx_order_user');
                $table->index('delegate_id', 'idx_order_delegate');
                $table->index('address_id', 'idx_order_address');
            });
        } else {
            Schema::table('order', function (Blueprint $table) {
                $table->dropForeign('order_branch_id_foreign');
                $table->dropIndex('idx_order_branch');
                $table->renameColumn('branch_id', 'address_id');
            });

            Schema::table('order', function (Blueprint $table) {
                $table->foreign('address_id')->references('id')->on('address');
                $table->index('address_id', 'idx_order_address');
            });
        }

        // 7. cart.branch_id -> address_id.
        if ($sqlite) {
            Schema::create('cart__new', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('address_id');
                $table->string('status', 20)->default('active');
                $table->timestamp('created_at')->nullable();
            });

            DB::statement('INSERT INTO "cart__new" (id, user_id, address_id, status, created_at)'
                . ' SELECT id, user_id, branch_id, status, created_at FROM "cart"');

            Schema::drop('cart');
            Schema::rename('cart__new', 'cart');

            Schema::table('cart', function (Blueprint $table) {
                $table->index('user_id', 'cart_user_id_foreign');
            });
        } else {
            // On MySQL the auto-created FK index shares the FK name.
            Schema::table('cart', function (Blueprint $table) {
                $table->dropForeign('cart_branch_id_foreign');
                $table->dropIndex('cart_branch_id_foreign');
                $table->renameColumn('branch_id', 'address_id');
            });

            Schema::table('cart', function (Blueprint $table) {
                $table->foreign('address_id')->references('id')->on('address');
            });
        }

        // 8. Drop the pivot first (it references cafe), then the cafe table.
        Schema::dropIfExists('cafe_user');
        Schema::dropIfExists('cafe');
    }

    public function down(): void
    {
        // Structure is restored; cafe/cafe_user rows are not recoverable.
        // cafe_branch must exist before cart/order can re-point at it.
        Schema::create('cafe', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 150);
            $table->string('contact_info', 200)->nullable();
            $table->string('image', 255)->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('created_by_admin_id')->unsigned()->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->default(now());

            $table->foreign('created_by_admin_id')->references('id')->on('user');
        });

        Schema::create('cafe_user', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('cafe_id')->unsigned()->nullable();
            $table->integer('user_id')->unsigned();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_available')->default(false);
            $table->timestamp('location_updated_at')->nullable();
            $table->timestamps();

            $table->foreign('cafe_id')->references('id')->on('cafe')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('user')->cascadeOnDelete();
            $table->unique(['cafe_id', 'user_id']);
        });

        Schema::table('address', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex('idx_address_user');
            $table->dropColumn(['user_id', 'contact_phones']);
        });

        Schema::table('address', function (Blueprint $table) {
            $table->unsignedInteger('cafe_id')->nullable()->after('id');
            $table->index('cafe_id', 'idx_cafe_branch_cafe');
            $table->foreign('cafe_id')->references('id')->on('cafe')->onDelete('cascade');
        });

        Schema::rename('address', 'cafe_branch');

        Schema::table('cart', function (Blueprint $table) {
            $table->dropForeign(['address_id']);
            $table->renameColumn('address_id', 'branch_id');
        });

        Schema::table('cart', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('cafe_branch');
        });

        Schema::table('order', function (Blueprint $table) {
            $table->dropForeign(['address_id']);
            $table->dropIndex('idx_order_address');
            $table->renameColumn('address_id', 'branch_id');
        });

        Schema::table('order', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('cafe_branch');
            $table->index('branch_id', 'idx_order_branch');
        });

        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'is_available', 'location_updated_at']);
        });
    }
};
