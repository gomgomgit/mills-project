<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depricarping_operational_targets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('parameter_metric');
            $table->string('target_range');
            $table->string('critical_limit');
            $table->string('operational_consequence_justification');
            $table->integer('sort_order');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depricarping_operational_targets');
    }
};
