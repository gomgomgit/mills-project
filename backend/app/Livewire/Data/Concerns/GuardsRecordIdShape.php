<?php

namespace App\Livewire\Data\Concerns;

use Illuminate\Support\Str;

/**
 * GuardsRecordIdShape — dipakai 18 layar Detail dan 18 layar Form (mode
 * edit) untuk menolak `{id}` URL yang bukan UUID SEBELUM sampai ke SQL
 * (audit 2026-10-04).
 *
 * Semua record stasiun ber-primary-key UUID. Di PostgreSQL, membandingkan
 * kolom `uuid` dengan teks bukan-UUID (mis. /data/threshing/abc) bukan
 * "tidak ketemu" melainkan QueryException (SQLSTATE 22P02) — yang lolos
 * dari `catch (ModelNotFoundException ...)` di mount() dan berakhir di
 * halaman error Laravel. SQLite tidak punya tipe uuid sehingga suite tes
 * tidak pernah melihatnya. Id berbentuk salah diperlakukan sama persis
 * dengan UUID yang tidak dikenal: keadaan "data tidak ditemukan".
 */
trait GuardsRecordIdShape
{
    protected function isRecordIdShapeValid(?string $id): bool
    {
        return $id !== null && Str::isUuid($id);
    }
}
