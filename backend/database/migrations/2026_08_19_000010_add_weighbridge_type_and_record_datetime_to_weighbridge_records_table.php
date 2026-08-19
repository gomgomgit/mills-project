<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * entity-catalog v5 (2026-08-19): weighbridge-record gained `weighbridge_type`
 * (enum: receive, dispatch) and `destination`; `arrival_datetime` +
 * `dispatch_datetime` were merged into a single `record_datetime` column
 * (arrival time for type=receive, dispatch time for type=dispatch).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weighbridge_records', function (Blueprint $table) {
            $table->enum('weighbridge_type', ['receive', 'dispatch'])->default('receive')->after('wb_card_number');
            $table->timestamp('record_datetime')->nullable()->after('weighbridge_type');
            $table->string('destination')->nullable()->after('estate_supplier');
        });

        // Backfill record_datetime from the column matching each row's default
        // type ('receive') before dropping the old columns, so existing rows
        // don't lose their timestamp. Left nullable at the DB level (no
        // ->change(), which needs doctrine/dbal, not installed in this
        // project) — required-ness for new writes is enforced in the
        // application layer (model/service), same pattern as other
        // "required in business terms, nullable in DB" fields in this schema.
        DB::table('weighbridge_records')->update([
            'record_datetime' => DB::raw('COALESCE(arrival_datetime, dispatch_datetime)'),
        ]);

        Schema::table('weighbridge_records', function (Blueprint $table) {
            $table->dropColumn(['arrival_datetime', 'dispatch_datetime']);
        });
    }

    public function down(): void
    {
        Schema::table('weighbridge_records', function (Blueprint $table) {
            $table->timestamp('arrival_datetime')->nullable()->after('wb_card_number');
            $table->timestamp('dispatch_datetime')->nullable()->after('arrival_datetime');
        });

        DB::table('weighbridge_records')->update([
            'arrival_datetime' => DB::raw('record_datetime'),
        ]);

        Schema::table('weighbridge_records', function (Blueprint $table) {
            $table->dropColumn(['weighbridge_type', 'record_datetime', 'destination']);
        });
    }
};
