<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-Rolle: Nur Admins verwalten Ärzte. Damit bestehende Installationen
 * verwaltbar bleiben, wird der älteste aktive Arzt zum Admin.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('role');
        });

        $first = DB::table('users')
            ->where('role', 'staff')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->value('id');

        if ($first !== null) {
            DB::table('users')->where('id', $first)->update(['is_admin' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
