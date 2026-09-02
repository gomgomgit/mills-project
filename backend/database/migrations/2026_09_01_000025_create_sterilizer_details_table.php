<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sterilizer_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sterilizer_record_id')->constrained('sterilizer_records')->cascadeOnDelete();
            $table->string('sterilizer_no')->nullable();
            $table->time('close_door_time')->nullable();
            $table->time('peak_1_time')->nullable();
            $table->time('exhaust_1_time')->nullable();
            $table->time('peak_2_time')->nullable();
            $table->time('exhaust_2_time')->nullable();
            $table->time('peak_3_time')->nullable();
            $table->time('exhaust_3_time')->nullable();
            $table->time('open_door_time')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->integer('number_of_cages')->nullable();
            $table->string('cages_status')->nullable();
            $table->boolean('checked_by_spv')->default(false);
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sterilizer_details');
    }
};
