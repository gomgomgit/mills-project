<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engine_room_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('engine_room_record_id')->constrained('engine_room_records')->cascadeOnDelete();
            $table->time('time_slot');
            $table->float('steam_turbine_inlet_pressure_bar')->nullable();
            $table->float('steam_turbine_inlet_temp_c')->nullable();
            $table->float('steam_turbine_exhaust_pressure_bar')->nullable();
            $table->float('steam_turbine_rpm')->nullable();
            $table->float('steam_turbine_alternator_bearing_temp_1_c')->nullable();
            $table->float('steam_turbine_alternator_bearing_temp_2_c')->nullable();
            $table->enum('diesel_gen_1_status', ['run', 'standby', 'off'])->nullable();
            $table->float('diesel_gen_1_load_kw')->nullable();
            $table->float('diesel_gen_1_amperage_a')->nullable();
            $table->float('diesel_gen_1_jacket_water_temp_c')->nullable();
            $table->float('diesel_gen_1_lube_oil_pressure_bar')->nullable();
            $table->enum('diesel_gen_2_status', ['run', 'standby', 'off'])->nullable();
            $table->float('diesel_gen_2_load_kw')->nullable();
            $table->float('diesel_gen_2_amperage_a')->nullable();
            $table->float('diesel_gen_2_jacket_water_temp_c')->nullable();
            $table->float('diesel_gen_2_lube_oil_pressure_bar')->nullable();
            $table->float('electrical_sync_total_factory_load_kw')->nullable();
            $table->float('electrical_sync_system_frequency_hz')->nullable();
            $table->float('electrical_sync_power_factor')->nullable();
            $table->float('electrical_sync_busbar_voltage_v')->nullable();
            $table->float('air_compressor_1_pressure_bar')->nullable();
            $table->float('compressor_2_pressure_bar')->nullable();
            $table->float('battery_charger_ups_voltage_v')->nullable();
            $table->float('fuel_tank_level')->nullable();
            $table->float('daily_energy_export_kwh')->nullable();
            $table->string('action_taken_maintenance_remark')->nullable();
            $table->string('findings')->nullable();
            $table->timestamps();

            $table->unique(['engine_room_record_id', 'time_slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engine_room_details');
    }
};
