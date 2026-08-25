<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kernel_plant_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kernel_plant_record_id')->constrained('kernel_plant_records')->cascadeOnDelete();
            $table->time('time_slot');
            $table->float('ripple_mill_1_amps')->nullable();
            $table->float('ripple_mill_2_amps')->nullable();
            $table->float('claybath_hydro_sg')->nullable();
            $table->float('kernel_silo_1_temp_c')->nullable();
            $table->float('kernel_silo_2_temp_c')->nullable();
            $table->float('kernel_moisture_percent')->nullable();
            $table->float('shell_loss_percent')->nullable();
            $table->integer('downtime_minutes')->nullable();
            $table->string('findings')->nullable();
            $table->timestamps();

            $table->unique(['kernel_plant_record_id', 'time_slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kernel_plant_details');
    }
};
