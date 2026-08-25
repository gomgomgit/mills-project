<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pressing_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pressing_record_id')->constrained('pressing_records')->cascadeOnDelete();
            $table->time('time_slot');
            $table->float('digester_temp_c')->nullable();
            $table->float('digester_level_percent')->nullable();
            $table->float('press_motor_current_amps')->nullable();
            $table->float('cone_hydraulic_pressure_bar')->nullable();
            $table->float('dilution_water_temp_c')->nullable();
            $table->string('downtime_reason')->nullable();
            $table->timestamps();

            $table->unique(['pressing_record_id', 'time_slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pressing_details');
    }
};
