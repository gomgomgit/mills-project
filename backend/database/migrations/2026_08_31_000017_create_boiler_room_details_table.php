<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boiler_room_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('boiler_room_record_id')->constrained('boiler_room_records')->cascadeOnDelete();
            $table->time('time_slot');
            $table->float('steam_pressure_bar')->nullable();
            $table->float('steam_temp_c')->nullable();
            $table->float('feed_water_temp_c')->nullable();
            $table->float('feed_water_tank_level_percent')->nullable();
            $table->float('boiler_water_level_percent')->nullable();
            $table->float('water_tds_ppm')->nullable();
            $table->float('water_ph')->nullable();
            $table->string('fuel_feed_rate')->nullable();
            $table->string('id_fan_load')->nullable();
            $table->string('sa_fan_load')->nullable();
            $table->float('exhaust_gas_temp_c')->nullable();
            $table->float('dust_collector_differential_pressure_mmh2o')->nullable();
            $table->enum('blowdown_executed', ['y', 'n'])->nullable();
            $table->enum('sootblowing_executed', ['y', 'n'])->nullable();
            $table->string('findings')->nullable();
            $table->timestamps();

            $table->unique(['boiler_room_record_id', 'time_slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boiler_room_details');
    }
};
