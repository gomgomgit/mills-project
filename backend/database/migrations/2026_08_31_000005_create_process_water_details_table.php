<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_water_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('process_water_record_id')->constrained('process_water_records')->cascadeOnDelete();
            $table->time('time_slot');
            $table->string('shift')->nullable();
            $table->string('inspector_id')->nullable();
            $table->float('raw_water_flow_m3h')->nullable();
            $table->float('clarified_water_flow_m3h')->nullable();
            $table->float('softener_inlet_ph')->nullable();
            $table->float('softener_outlet_hardness_ppm')->nullable();
            $table->float('alum_dosing_kgh')->nullable();
            $table->float('polymer_dosing_gh')->nullable();
            $table->float('boiler_feed_tank_temp_c')->nullable();
            $table->float('boiler_feed_water_ph')->nullable();
            $table->float('boiler_feed_tds_ppm')->nullable();
            $table->string('action_taken_status')->nullable();
            $table->string('findings')->nullable();
            $table->timestamps();

            $table->unique(['process_water_record_id', 'time_slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_water_details');
    }
};
