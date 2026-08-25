<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pressing_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('station_id')->constrained('stations')->restrictOnDelete();
            $table->string('presser_id');
            $table->date('date');
            $table->string('note')->nullable();
            $table->foreignUuid('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft_ongoing', 'draft_paused', 'saved', 'synced']);
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pressing_records');
    }
};
