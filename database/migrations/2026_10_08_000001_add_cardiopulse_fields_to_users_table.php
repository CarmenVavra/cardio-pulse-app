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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('patient')->after('id')->index();
            $table->string('username')->nullable()->unique()->after('role');
            $table->string('title', 20)->nullable()->after('username');
            $table->string('phone', 40)->nullable()->after('email');
            $table->string('pin')->nullable()->after('password');
            $table->string('available_until', 5)->nullable()->after('pin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropUnique(['username']);
            $table->dropColumn(['role', 'username', 'title', 'phone', 'pin', 'available_until']);
        });
    }
};
