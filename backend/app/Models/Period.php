<?php

namespace App\Models;

use App\Enums\PeriodStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Period (Periode Pelaporan) — screen-128--kelola-periode-pelaporan /
 * usecase-128, usecase-140--tutup-buka-periode-pelaporan.
 *
 * Rentang tanggal pelaporan resmi per mill (Business Unit) dan jenis
 * stasiun — satuan periode untuk seluruh laporan Full Cycle per Stasiun
 * (screen-128 s/d screen-139).
 *
 * Cakupannya adalah mill + station_type, BUKAN Production Line (keputusan
 * user 2026-09-22): satu periode berlaku untuk semua Production Line di
 * mill tersebut. `station_type` NULL berarti periode berlaku untuk SEMUA
 * jenis stasiun di mill itu — tidak ada FK ke `stations` maupun
 * `production_lines`, cakupan sengaja di level jenis stasiun.
 *
 * Entitas ini berdiri di luar hierarki Corporate -> Company -> Business
 * Unit -> Production Line -> Station.
 *
 * KUNCI PERIODE TERTUTUP: saat status = closed, record stasiun mana pun
 * (18 entitas *-record) yang kolom `date`-nya jatuh di dalam
 * [start_date, end_date] untuk (business_unit_id, station_type) yang cocok
 * tidak boleh di-INSERT, di-UPDATE, maupun diubah kolom
 * checked_by/acknowledged_by-nya. Penentuan periode memakai `record.date`
 * (tanggal kejadian di pabrik), BUKAN created_at atau waktu sync.
 * Penegakannya WAJIB di layer service backend — aplikasi mobile mengirim
 * record langsung ke POST /api/{stasiun}-records sehingga kunci di layar
 * bisa dilewati.
 */
class Period extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'business_unit_id',
        'station_type',
        'name',
        'start_date',
        'end_date',
        'status',
        'closed_by',
        'closed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => PeriodStatus::class,
        'closed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
