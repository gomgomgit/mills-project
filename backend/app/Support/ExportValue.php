<?php

namespace App\Support;

/**
 * ExportValue — format nilai sel untuk file ekspor CSV/Excel (laporan
 * stasiun dan Data Browser), temuan audit 2026-10-04 #8.
 *
 * Bedanya dengan App\Support\Display (format TAMPILAN layar): sel kosong
 * tetap KOSONG (null), bukan "-", dan angka tidak diberi pemisah ribuan —
 * file ekspor harus tetap bisa dihitung di spreadsheet.
 */
class ExportValue
{
    /** Label katup pemanas uap Storage Tank — sama dengan opsi di form. */
    public const VALVE_LABELS = [
        'closed' => 'Closed',
        'open_1_4' => 'Open 1/4',
        'open_1_2' => 'Open 1/2',
    ];

    /** Label status record (Tersimpan, Tersinkron, …); kosong → null. */
    public static function status(mixed $status): ?string
    {
        $label = Display::status($status);

        return $label === '-' ? null : $label;
    }

    /**
     * Jam "HH:MM" dari "HH:MM:SS", "HH:MM", atau tanggal-jam. Slot waktu
     * dan jam di setiap ekspor tampil seragam tanpa detik.
     */
    public static function time(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        $text = trim((string) $value);

        if (preg_match('/(\d{1,2}):(\d{2})(?::\d{2}(?:\.\d+)?)?$/', $text, $m) === 1) {
            return str_pad($m[1], 2, '0', STR_PAD_LEFT).':'.$m[2];
        }

        return $text;
    }

    /** Tanggal-jam "YYYY-MM-DD HH:MM" (tanpa detik). */
    public static function dateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        $text = trim((string) $value);

        return preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/', $text, $m) === 1
            ? $m[1].' '.$m[2]
            : $text;
    }

    public static function valve(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::VALVE_LABELS[(string) $value] ?? (string) $value;
    }

    /**
     * Label pilihan enum detail stasiun (on/off/fault, run/stop/standby,
     * y/n) — sama dengan layar Detail (Display::OPTION_LABELS); kosong → null.
     */
    public static function option(mixed $value): ?string
    {
        $label = Display::option($value);

        return $label === '-' ? null : $label;
    }

    /** Boolean ya/tidak sebagaimana layar Detail ("Ya" / "Tidak"). */
    public static function yesNo(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value ? 'Ya' : 'Tidak';
    }
}
