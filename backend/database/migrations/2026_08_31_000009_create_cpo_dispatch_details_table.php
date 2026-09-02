<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpo_dispatch_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpo_dispatch_record_id')->constrained('cpo_dispatch_records')->cascadeOnDelete();
            $table->date('event_date');
            $table->string('shift')->nullable();
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->string('waybill_number')->nullable();
            $table->string('tanker_plate_no')->nullable();
            $table->string('transport_company')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('storage_tank_source')->nullable();
            $table->string('seal_no_top')->nullable();
            $table->string('seal_no_bottom')->nullable();
            $table->float('gross_weight_mt')->nullable();
            $table->float('tare_weight_mt')->nullable();
            $table->float('net_weight_mt')->nullable();
            $table->float('ffa_percent')->nullable();
            $table->float('moisture_percent')->nullable();
            $table->float('impurities_percent')->nullable();
            $table->float('dobi')->nullable();
            $table->string('destination_buyer')->nullable();
            $table->string('weighbridge_operator')->nullable();
            $table->string('findings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpo_dispatch_details');
    }
};
