<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom (fork) feature: asset reservation system.
 *
 * This migration is upgrade-aware so a single code path serves two populations:
 *
 *  1. Fresh upstream install  -> the tables have never existed, so we CREATE them.
 *  2. Upgrade from the old     -> the legacy `reservations` / `asset_reservation`
 *     v5.4.1-PATCH production      tables already hold live data, so we RENAME them
 *                                  to the `sw_`-prefixed names (preserving rows + FKs)
 *                                  instead of creating empty tables.
 *
 * Only tables we introduced ourselves get the `sw_` prefix; native Snipe-IT tables
 * are never renamed. The prefix keeps these custom tables from ever colliding with a
 * table a future upstream version might add.
 *
 * Column types intentionally use increments()/unsignedInteger() (int, not bigint) to
 * stay byte-compatible with the legacy tables on the rename path and with the int PKs
 * of `users`/`assets`, so both populations converge on an identical schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- sw_reservations -------------------------------------------------
        if (Schema::hasTable('reservations') && ! Schema::hasTable('sw_reservations')) {
            Schema::rename('reservations', 'sw_reservations');
        } elseif (! Schema::hasTable('sw_reservations')) {
            Schema::create('sw_reservations', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->unsignedInteger('user_id');
                $table->dateTime('start');
                $table->dateTime('end');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('user_id')->references('id')->on('users');
            });
        }

        // --- sw_asset_reservation (pivot) ------------------------------------
        if (Schema::hasTable('asset_reservation') && ! Schema::hasTable('sw_asset_reservation')) {
            Schema::rename('asset_reservation', 'sw_asset_reservation');
        } elseif (! Schema::hasTable('sw_asset_reservation')) {
            Schema::create('sw_asset_reservation', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('asset_id');
                $table->unsignedInteger('reservation_id');
                $table->timestamps();

                $table->unique(['asset_id', 'reservation_id']);
                $table->foreign('asset_id')->references('id')->on('assets')->onDelete('cascade');
                $table->foreign('reservation_id')->references('id')->on('sw_reservations')->onDelete('cascade');
            });
        }

        // --- reconcile legacy schema drift -----------------------------------
        // The legacy `reservations` table never had a soft-delete column even though
        // the old model referenced one; add it on the rename path. No-op for a fresh
        // create (which already includes softDeletes above).
        if (Schema::hasTable('sw_reservations') && ! Schema::hasColumn('sw_reservations', 'deleted_at')) {
            Schema::table('sw_reservations', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sw_asset_reservation');
        Schema::dropIfExists('sw_reservations');
    }
};
