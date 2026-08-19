<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weighbridge_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('station_id')->constrained('stations')->restrictOnDelete();
            $table->string('wb_card_number');
            $table->timestamp('arrival_datetime');
            $table->timestamp('dispatch_datetime')->nullable();
            $table->string('vehicle_number');
            $table->string('driver_name');
            $table->string('estate_supplier');
            $table->string('division')->nullable();
            $table->string('block')->nullable();
            $table->float('gross_weight');
            $table->float('tare_weight')->nullable();
            $table->float('net_weight')->nullable();
            $table->float('quantity')->nullable();
            $table->foreignUuid('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft_ongoing', 'draft_paused', 'saved', 'synced']);
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weighbridge_records');
    }
};
