<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('threshing_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('threshing_record_id')->constrained('threshing_records')->cascadeOnDelete();
            $table->time('time_slot');
            $table->float('ffb_throughput_mt_hour')->nullable();
            $table->float('thresher_drum_speed_rpm')->nullable();
            $table->float('motor_current_amps')->nullable();
            $table->float('unstripped_bunch_count_percent')->nullable();
            $table->float('empty_bunch_oil_loss_percent')->nullable();
            $table->string('downtime_reason')->nullable();
            $table->timestamps();

            $table->unique(['threshing_record_id', 'time_slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('threshing_details');
    }
};
