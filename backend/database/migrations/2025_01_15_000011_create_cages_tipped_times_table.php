<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cages_tipped_times', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cages_track_record_id')->constrained('cages_track_records')->cascadeOnDelete();
            $table->integer('tipped_hour');
            $table->text('checked_cage_numbers');
            $table->integer('total_cages');
            $table->integer('cages_remain');
            $table->timestamps();

            $table->unique(['cages_track_record_id', 'tipped_hour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cages_tipped_times');
    }
};
