<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kernel_plant_operational_targets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('equipment_parameter');
            $table->string('target_benchmark');
            $table->string('corrective_action_plan');
            $table->integer('sort_order');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kernel_plant_operational_targets');
    }
};
