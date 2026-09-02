<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clarification_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('clarification_record_id')->constrained('clarification_records')->cascadeOnDelete();
            $table->time('time_slot');
            $table->float('clarification_tank_temp_c')->nullable();
            $table->float('oil_tank_temperature_c')->nullable();
            $table->float('sludge_tank_temp_c')->nullable();
            $table->float('buffer_tank_level_percent')->nullable();
            $table->float('pure_oil_production_rate_ton_hour')->nullable();
            $table->float('downtime_mins')->nullable();
            $table->string('findings')->nullable();
            $table->timestamps();

            $table->unique(['clarification_record_id', 'time_slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clarification_details');
    }
};
