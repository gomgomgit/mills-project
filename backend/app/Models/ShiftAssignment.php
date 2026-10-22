<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ShiftAssignment — satu orang pada satu SEL (shift × tanggal)
 * (screen-171--jadwal-shift / usecase-177--jadwal-shift, entity-catalog
 * entitas `shift-assignment`).
 *
 * `work_date` adalah TANGGAL MULAI shift (shift lintas tengah malam tidak
 * dipecah ke dua tanggal). Kolomnya `date`, dibiarkan TANPA cast: SQLite
 * memulangkan string 'YYYY-MM-DD' sementara PostgreSQL memulangkan tanggal
 * yang sama — ShiftScheduleService membandingkan dan mengelompokkannya
 * sebagai string 'YYYY-MM-DD' di kedua basis data, dan cast ke Carbon hanya
 * akan menempelkan jam 00:00:00 yang ikut terbawa ke perbandingan.
 *
 * Penugasan INFORMASIONAL: ia tidak memengaruhi hak input record dan tidak
 * menandai shift_id pada record mana pun.
 */
class ShiftAssignment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'shift_id',
        'user_id',
        'work_date',
        'created_by',
    ];

    /**
     * withTrashed: baris penugasan tetap menunjuk ke shift yang sudah
     * ditutup (soft delete) — histori sebelum tanggal penutupan terbaca.
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
