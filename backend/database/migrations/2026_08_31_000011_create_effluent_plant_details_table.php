<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('effluent_plant_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('effluent_plant_record_id')->constrained('effluent_plant_records')->cascadeOnDelete();
            $table->time('time_slot');
            $table->float('anaerobic_pond_1_ph')->nullable();
            $table->float('anaerobic_pond_1_temp_c')->nullable();
            $table->float('anaerobic_pond_2_ph')->nullable();
            $table->float('anaerobic_pond_2_temp_c')->nullable();
            $table->float('cooling_pond_ph')->nullable();
            $table->float('cooling_pond_temp_c')->nullable();
            $table->enum('biogas_flare_status', ['on', 'off', 'fault'])->nullable();
            $table->float('biogas_flow_rate_m3h')->nullable();
            $table->float('raw_pome_feed_rate_m3h')->nullable();
            $table->float('effluent_discharge_flow_rate_m3h')->nullable();
            $table->float('final_discharge_ph')->nullable();
            $table->float('final_discharge_bod_mgl_lab')->nullable();
            $table->float('final_discharge_cod_mgl_lab')->nullable();
            $table->float('final_discharge_tss_mgl_lab')->nullable();
            $table->enum('dosing_pump_1_status', ['run', 'stop'])->nullable();
            $table->float('chemical_consumed_kgl')->nullable();
            $table->enum('sludge_dewatering_status', ['run', 'stop'])->nullable();
            $table->string('remarks_maintenance_actions')->nullable();
            $table->string('findings')->nullable();
            $table->timestamps();

            $table->unique(['effluent_plant_record_id', 'time_slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('effluent_plant_details');
    }
};
