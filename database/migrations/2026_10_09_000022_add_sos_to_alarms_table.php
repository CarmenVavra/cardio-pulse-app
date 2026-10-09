<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notfalltaste (SOS): Alarme ohne Messung, mit optionalem Standort (verschlüsselt,
 * wird beim Quittieren gelöscht), „Übernommen von“ für mehrere gleichzeitige Alarme
 * und „Fehlalarm“-Meldung des Patienten. Dazu die widerrufbare Einwilligung des
 * Patienten, im Notfall seinen Standort zu übermitteln.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('alarms', function (Blueprint $table) {
            $table->foreignId('measurement_id')->nullable()->change();
            $table->string('type', 20)->default('measurement')->after('measurement_id');
            $table->text('location')->nullable()->after('type');
            $table->timestamp('located_at')->nullable()->after('location');
            $table->foreignId('claimed_by')->nullable()->after('located_at')->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable()->after('claimed_by');
            $table->timestamp('false_alarm_at')->nullable()->after('claimed_at');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->timestamp('location_consent_at')->nullable()->after('gp_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('location_consent_at');
        });

        // SOS-Alarme haben keine Messung – vor dem Zurückstellen auf NOT NULL entfernen.
        DB::table('alarms')->whereNull('measurement_id')->delete();

        Schema::table('alarms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('claimed_by');
            $table->dropColumn(['type', 'location', 'located_at', 'claimed_at', 'false_alarm_at']);
            $table->foreignId('measurement_id')->nullable(false)->change();
        });
    }
};
