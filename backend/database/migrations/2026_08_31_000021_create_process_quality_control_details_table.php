<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_quality_control_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('process_quality_control_record_id')->constrained('process_quality_control_records')->cascadeOnDelete();
            $table->time('time_slot');
            $table->string('shift')->nullable();
            $table->float('fruit_press_oil_loss_in_sludge_percent')->nullable();
            $table->float('fruit_press_oil_loss_in_fibre_percent')->nullable();
            $table->float('purifier_clarification_balance_inlet_temp_c')->nullable();
            $table->float('purifier_clarification_balance_backpressure_bar')->nullable();
            $table->float('vacuum_drying_station_drier_temp_c')->nullable();
            $table->float('vacuum_drying_station_vacuum_pressure_bar')->nullable();
            $table->float('decanter_centrifuge_feed_rate_mth')->nullable();
            $table->float('decanter_centrifuge_oil_loss_in_cake_percent')->nullable();
            $table->float('final_storage_ffa_percent')->nullable();
            $table->float('final_storage_moisture_content_percent')->nullable();
            $table->float('final_storage_impurities_dirt_percent')->nullable();
            $table->float('final_storage_dobi_index')->nullable();
            $table->string('qc_inspector_id')->nullable();
            $table->string('qc_engineering_corrective_actions')->nullable();
            $table->string('findings')->nullable();
            $table->timestamps();

            $table->unique(['process_quality_control_record_id', 'time_slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_quality_control_details');
    }
};
