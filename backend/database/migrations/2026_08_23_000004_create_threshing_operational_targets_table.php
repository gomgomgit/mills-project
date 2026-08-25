<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('threshing_operational_targets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('parameter');
            $table->string('standard_operational_target');
            $table->string('action_plan_on_deviation');
            $table->integer('sort_order');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('threshing_operational_targets');
    }
};
