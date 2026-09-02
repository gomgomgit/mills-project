<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kernel_dispatch_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kernel_dispatch_record_id')->constrained('kernel_dispatch_records')->cascadeOnDelete();
            $table->date('event_date');
            $table->string('shift')->nullable();
            $table->string('weighbridge_ticket_no')->nullable();
            $table->string('waybill_number')->nullable();
            $table->string('transporter_contractor')->nullable();
            $table->string('vehicle_plate_no')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('silo_source_id')->nullable();
            $table->string('destination_buyer')->nullable();
            $table->float('gross_weight_mt')->nullable();
            $table->float('tare_weight_mt')->nullable();
            $table->float('net_weight_mt')->nullable();
            $table->float('kernel_moisture_percent')->nullable();
            $table->float('dirt_impurities_percent')->nullable();
            $table->float('ffa_percent')->nullable();
            $table->float('broken_kernel_percent')->nullable();
            $table->string('security_seal_no_top')->nullable();
            $table->string('security_seal_no_bottom')->nullable();
            $table->string('weighbridge_operator_id')->nullable();
            $table->enum('remarks_gate_status', ['released', 'not_released'])->nullable();
            $table->string('findings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kernel_dispatch_details');
    }
};
