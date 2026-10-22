<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Melebarkan users.role agar menerima 7 role baru setara Admin (keputusan
 * produk 2026-10-22): engineer, shift_supervisor, maintenance, mekanik,
 * logistics, qa_qc, mill_admin.
 *
 * Pola yang sama dengan 2026_08_31_000001_widen_station_type_enum: MySQL
 * butuh ALTER TABLE ... MODIFY; PostgreSQL mengompilasi enum() ke varchar +
 * CHECK bernama (users_role_check) yang di-drop lalu dibuat ulang; SQLite
 * (Laravel 11+) juga memberi enum() sebuah CHECK, sehingga kolom dibangun
 * ulang lewat change().
 */
return new class extends Migration
{
    private const OLD_ROLES = ['operator', 'supervisor', 'mill_management', 'admin'];

    private const NEW_ROLES = [
        'operator', 'supervisor', 'mill_management', 'admin',
        'engineer', 'shift_supervisor', 'maintenance', 'mekanik', 'logistics', 'qa_qc', 'mill_admin',
    ];

    public function up(): void
    {
        $this->applyCheck(self::NEW_ROLES);
    }

    public function down(): void
    {
        // Baris dengan role baru akan melanggar constraint lama — petakan ke
        // 'admin' (perilaku efektifnya) sebelum constraint dipersempit.
        DB::table('users')
            ->whereNotIn('role', self::OLD_ROLES)
            ->update(['role' => 'admin']);

        $this->applyCheck(self::OLD_ROLES);
    }

    private function applyCheck(array $roles): void
    {
        $driver = DB::getDriverName();
        $quoted = "'".implode("', '", $roles)."'";

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM({$quoted}) NOT NULL");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ({$quoted}))");

            return;
        }

        Schema::table('users', function (Blueprint $table) use ($roles) {
            $table->enum('role', $roles)->change();
        });
    }
};
