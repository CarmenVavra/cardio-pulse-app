<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hypotonie wird nicht mehr weiß, sondern blau („Blue Ice“ laut Projektkonzept) dargestellt –
 * der gespeicherte Ampelstatus wird entsprechend umbenannt.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->rename('white', 'blue');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->rename('blue', 'white');
    }

    private function rename(string $from, string $to): void
    {
        DB::table('measurements')->where('status', $from)->update(['status' => $to]);
        DB::table('monthly_reports')->where('worst_status', $from)->update(['worst_status' => $to]);
    }
};
