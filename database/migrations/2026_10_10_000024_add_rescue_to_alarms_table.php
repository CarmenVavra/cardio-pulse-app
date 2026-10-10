<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notfalltaste: Der Patient gibt an, ob er selbst den Notruf wählt oder das Krankenhaus
 * die Rettung rufen soll; das Krankenhaus dokumentiert, wer die Rettung verständigt hat.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('alarms', function (Blueprint $table) {
            $table->string('patient_response', 20)->nullable()->after('false_alarm_at');
            $table->timestamp('responded_at')->nullable()->after('patient_response');
            $table->foreignId('rescue_called_by')->nullable()->after('responded_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rescue_called_at')->nullable()->after('rescue_called_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alarms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rescue_called_by');
            $table->dropColumn(['patient_response', 'responded_at', 'rescue_called_at']);
        });
    }
};
