<?php

namespace App\Models;

use App\Enums\PeriodStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PeriodStation (Stasiun dalam Periode) — entity-catalog v18: entitas
 * `period-station`. screen-128--kelola-periode-pelaporan / usecase-140.
 *
 * Satu jenis stasiun di dalam satu Periode Pelaporan, beserta status
 * tutup/bukanya. Dipisahkan dari `periods` pada 2026-09-25: sebelumnya tiap
 * jenis stasiun menuntut barisnya sendiri di `periods` dengan nama, rentang
 * tanggal, dan status yang diulang-ulang, sehingga satu periode di 17 stasiun
 * berarti 17 baris dan 17 kali menutup.
 *
 * STATUS ADA DI SINI, BUKAN DI INDUK, karena stasiun tidak selesai serentak —
 * Admin dapat menutup Sterilizer sementara Clarification masih terbuka, dan
 * itu memang bentuk data yang ditemukan di DB dev saat keputusan ini diambil.
 *
 * UNIQUE (period_id, station_type) ditegakkan DB: tanpa itu satu periode dapat
 * punya dua baris untuk jenis stasiun yang sama dengan status berbeda, dan
 * tidak ada jawaban benar atas "stasiun ini tertutup atau tidak".
 *
 * Transisi status yang diizinkan: draft -> open, open -> closed,
 * closed -> open (buka kembali). Transisi berlaku PER BARIS, bukan per
 * periode, dan hanya Admin yang boleh menutup/membuka; penegakannya di layer
 * service. closed_by dan closed_at wajib terisi saat status = closed dan
 * dikosongkan kembali saat dibuka ulang.
 */
class PeriodStation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'period_id',
        'station_type',
        'status',
        'closed_by',
        'closed_at',
    ];

    /**
     * `station_type` SENGAJA TIDAK di-cast ke App\Enums\StationType, berbeda
     * dari Station::$casts['type']. Kolom ini mewarisi peran
     * `periods.station_type`, yang juga string mentah dan dibandingkan sebagai
     * string di belasan tempat (service laporan, filter layar, payload API).
     * Sebuah cast enum akan membuat setiap `$ps->station_type === 'sterilizer'`
     * yang tersisa menjadi false tanpa error — kelas bug yang sama dengan yang
     * dijaga Period::guardMovedAttribute(). Pakai StationType::Sterilizer->value
     * saat menyebut satu jenis tertentu.
     */
    protected $casts = [
        'status' => PeriodStatus::class,
        'closed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    /**
     * Master jenis stasiun — di-join pada `code`, bukan `id` (lihat
     * create_station_types_table untuk alasannya). Dipakai untuk label
     * tampilan; jangan dipakai untuk membandingkan jenis.
     */
    public function stationTypeRef(): BelongsTo
    {
        return $this->belongsTo(StationType::class, 'station_type', 'code');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
