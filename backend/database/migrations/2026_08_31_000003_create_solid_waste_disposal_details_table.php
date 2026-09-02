<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solid_waste_disposal_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('solid_waste_disposal_record_id')->constrained('solid_waste_disposal_records')->cascadeOnDelete();
            $table->date('event_date');
            $table->string('shift')->nullable();
            $table->string('weighbridge_ticket_no')->nullable();
            $table->string('vehicle_no')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('solid_waste_type')->nullable();
            $table->string('source_station')->nullable();
            $table->float('gross_weight_mt')->nullable();
            $table->float('tare_weight_mt')->nullable();
            $table->float('net_weight_mt')->nullable();
            $table->string('disposal_utilization_site')->nullable();
            $table->string('purpose_end_use')->nullable();
            $table->string('gate_pass_no')->nullable();
            $table->string('security_seal_no')->nullable();
            $table->string('operator_id')->nullable();
            $table->string('remarks')->nullable();
            $table->string('findings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solid_waste_disposal_details');
    }
};
