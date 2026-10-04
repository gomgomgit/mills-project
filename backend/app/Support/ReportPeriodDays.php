<?php

namespace App\Support;

use App\Models\Period;
use Illuminate\Support\Carbon;

/**
 * ReportPeriodDays — jumlah hari yang DIHARAPKAN tercatat untuk metrik
 * kelengkapan laporan stasiun (slot terisi / slot diharapkan, hari dengan
 * trip / hari periode).
 *
 * KENAPA ADA (temuan audit 2026-10-04 #3). Penyebut kelengkapan dulu
 * memakai SELURUH hari periode, termasuk hari yang belum terjadi: periode
 * 01–09 Okt yang dibaca pada 04 Okt tampil ~32% walau keempat hari yang
 * sudah lewat terisi penuh. Hari yang belum terjadi tidak mungkin dicatat,
 * jadi untuk periode yang masih berjalan penyebutnya dihentikan di HARI INI
 * (zona aplikasi, WIB). Periode yang sudah selesai tidak berubah sama
 * sekali; periode yang belum mulai menghasilkan 0 hari (penyebut 0 →
 * layar menampilkan "—", bukan 0%).
 */
class ReportPeriodDays
{
    /** Jumlah hari inklusif seluruh periode. */
    public static function inPeriod(Period $period): int
    {
        return (int) $period->start_date->copy()->startOfDay()
            ->diffInDays($period->end_date->copy()->startOfDay()) + 1;
    }

    /** true bila tanggal akhir periode masih di masa depan (setelah hari ini). */
    public static function isRunning(Period $period): bool
    {
        return $period->end_date->toDateString() > self::today()->toDateString();
    }

    /**
     * Hari yang sudah terjadi di dalam periode: start..min(end, hari ini),
     * inklusif. 0 bila periode belum mulai.
     */
    public static function counted(Period $period): int
    {
        if (! self::isRunning($period)) {
            return self::inPeriod($period);
        }

        $start = Carbon::parse($period->start_date->toDateString(), AppTime::zone())->startOfDay();
        $today = self::today();

        if ($today->lt($start)) {
            return 0;
        }

        return (int) $start->diffInDays($today) + 1;
    }

    private static function today(): Carbon
    {
        return Carbon::today(AppTime::zone());
    }
}
