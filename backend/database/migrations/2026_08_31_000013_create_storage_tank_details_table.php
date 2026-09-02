<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_tank_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('storage_tank_record_id')->constrained('storage_tank_records')->cascadeOnDelete();
            $table->time('time_slot');
            $table->float('cpo_sounding_depth_mm')->nullable();
            $table->float('water_dip_bottom_depth_mm')->nullable();
            $table->float('net_oil_depth_mm')->nullable();
            $table->float('oil_temperature_top_c')->nullable();
            $table->float('oil_temperature_middle_c')->nullable();
            $table->float('oil_temperature_bottom_c')->nullable();
            $table->float('average_temperature_c')->nullable();
            $table->float('calculated_volume_m3')->nullable();
            $table->float('calculated_weight_mt')->nullable();
            $table->float('ffa_percent')->nullable();
            $table->float('moisture_content_percent')->nullable();
            $table->float('impurities_dirt_percent')->nullable();
            $table->float('dobi_index')->nullable();
            $table->enum('steam_heating_valve_status', ['closed', 'open_1_4', 'open_1_2'])->nullable();
            $table->string('tank_structural_condition')->nullable();
            $table->string('inspector_name')->nullable();
            $table->string('findings')->nullable();
            $table->timestamps();

            $table->unique(['storage_tank_record_id', 'time_slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_tank_details');
    }
};
