<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('monthly_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->timestamp('sent_at');
            $table->unsignedInteger('measurement_count');
            $table->unsignedSmallInteger('avg_systolic')->nullable();
            $table->unsignedSmallInteger('avg_diastolic')->nullable();
            $table->string('worst_status', 10)->nullable();
            $table->timestamps();

            $table->unique(['patient_id', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_reports');
    }
};
