<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.sessions_revoked_at — kapan seluruh sesi web akun ini terakhir
 * dicabut (keputusan 2026-10-05: Reset Password oleh Admin mencabut semua
 * token Sanctum DAN sesi web akun itu).
 *
 * SESSION_DRIVER=file, jadi sesi tidak bisa dihapus per user dengan query.
 * Sebagai gantinya setiap sesi menyimpan stempel waktu login
 * (EnsureUserIsActive::SESSION_AUTH_AT) dan EnsureUserIsActive
 * mengeluarkan sesi yang stempelnya lebih tua dari kolom ini. Presisi
 * mikrodetik supaya login ulang di detik yang sama dengan reset tidak ikut
 * tercabut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('sessions_revoked_at', 6)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sessions_revoked_at');
        });
    }
};
