<?php

namespace App\Support\Concerns;

use App\Services\ShiftService;

/**
 * StampsShift — penandaan `shift_id` yang dipakai bersama ke-18
 * *RecordService stasiun.
 *
 * MENJAWAB SATU PERTANYAAN: record ini dihasilkan pada shift mana. Aturannya
 * sendiri hidup di {@see ShiftService::resolveShiftIdForSave()} — trait ini
 * hanya titik panggilnya, supaya ke-18 service memanggil hal yang sama alih
 * -alih menyalin aturannya masing-masing. Pola yang sama dengan
 * {@see EnforcesPeriodLock}.
 *
 * ── KAPAN DIPANGGIL: SAAT MEMBUAT, BUKAN SAAT MENGUBAH ──────────────────
 *
 * `shift_id` adalah STEMPEL SEJARAH, bukan atribut yang ikut disunting. Ia
 * ditulis sekali pada create() dan TIDAK PERNAH dihitung ulang oleh update():
 * sebuah record shift malam yang disunting keesokan paginya pukul 09:00 akan
 * berpindah menjadi record shift pagi bila ia dihitung ulang, dan perpindahan
 * itu tidak akan terlihat oleh siapa pun. Sama dengan `production_line_id`,
 * yang juga snapshot saat penulisan dan sengaja dibiarkan menyimpang dari
 * konfigurasi hari ini.
 *
 * ── DUA JALUR, SATU PANGGILAN ───────────────────────────────────────────
 *
 * Mobile mengirim `shift_id` (perangkat yang menyetempel, karena server hanya
 * tahu waktu SINKRONISASI); web tidak mengirim apa pun dan server menurunkan
 * dari jam simpan. Pemanggil tidak perlu tahu ia sedang melayani yang mana —
 * KEHADIRAN `shift_id` di payload itulah yang membedakan keduanya, dan itu
 * cukup. Menambahkan penanda jalur eksplisit hanya akan menciptakan keadaan
 * kedua yang bisa berselisih dengan isi payload-nya sendiri.
 *
 * ── NULL ITU NORMAL, BUKAN KEGAGALAN ────────────────────────────────────
 *
 * Celah jadwal diizinkan oleh rancangan dan sebuah line boleh belum punya
 * shift sama sekali, jadi null adalah hasil yang sah dan sering. Pemanggil
 * TIDAK boleh menolak penyimpanan karenanya.
 */
trait StampsShift
{
    /**
     * Nilai `shift_id` untuk record yang sedang dibuat.
     *
     * @param  array<string, mixed>  $data  payload create() apa adanya; hanya
     *                                      kunci `shift_id` yang dibaca
     * @param  string  $productionLineId  line yang SUDAH diresolve server dari
     *                                    stasiunnya — bukan nilai kiriman klien
     */
    protected function stampShiftId(array $data, string $productionLineId): ?string
    {
        $clientShiftId = $data['shift_id'] ?? null;

        return app(ShiftService::class)->resolveShiftIdForSave(
            is_string($clientShiftId) ? $clientShiftId : null,
            $productionLineId,
        );
    }
}
