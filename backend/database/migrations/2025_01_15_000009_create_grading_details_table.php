<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('grading_record_id')->constrained('grading_records')->cascadeOnDelete();
            $table->foreignUuid('grading_parameter_id')->constrained('grading_parameters')->restrictOnDelete();
            $table->float('quantity');
            $table->enum('uom', ['kg', 'bunch']);
            $table->float('percentage');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_details');
    }
};
