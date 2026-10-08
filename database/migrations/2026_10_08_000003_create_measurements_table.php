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
        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('systolic');
            $table->unsignedSmallInteger('diastolic');
            $table->unsignedSmallInteger('pulse')->nullable();
            $table->string('status', 10)->index();
            $table->string('method', 20)->default('manual');
            $table->json('symptoms')->nullable();
            $table->boolean('rested')->default(false);
            $table->boolean('medication_taken')->default(false);
            $table->timestamp('measured_at');
            $table->timestamp('symptom_free_confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'measured_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('measurements');
    }
};
