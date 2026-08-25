<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pressing_operational_targets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('parameter_metric');
            $table->string('target_operating_range');
            $table->string('critical_trigger_action_limit');
            $table->integer('sort_order');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pressing_operational_targets');
    }
};
