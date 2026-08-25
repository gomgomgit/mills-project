<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depricarping_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('depricarping_record_id')->constrained('depricarping_records')->cascadeOnDelete();
            $table->time('time_slot');
            $table->float('fan_static_pressure_mmh2o')->nullable();
            $table->float('polishing_drum_speed_rpm')->nullable();
            $table->float('air_velocity_ms')->nullable();
            $table->float('fibre_moisture_percent')->nullable();
            $table->float('kernel_recovery_in_fibre_percent')->nullable();
            $table->float('nut_silo_1_temp_c')->nullable();
            $table->float('nut_silo_2_temp_c')->nullable();
            $table->integer('downtime_minutes')->nullable();
            $table->string('findings')->nullable();
            $table->timestamps();

            $table->unique(['depricarping_record_id', 'time_slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depricarping_details');
    }
};
