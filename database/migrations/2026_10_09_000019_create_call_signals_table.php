<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verbindungsaufbau der Videosprechstunde (WebRTC-Signalisierung): Angebot, Antwort und
 * Netzwerkkandidaten zwischen Arzt und Patient. Bild und Ton laufen direkt zwischen den
 * Browsern; die Einträge werden beim Auflegen gelöscht (sie enthalten IP-Adressen).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('call_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->cascadeOnDelete();
            $table->string('sender', 10);
            $table->string('type', 20);
            $table->text('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['call_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_signals');
    }
};
