<?php

namespace App\Support;

use App\Enums\RecordStatus;
use Illuminate\Support\Carbon;

/**
 * Display — format tampilan Bahasa Indonesia untuk layar data stasiun web
 * (Data Browser, Detail, Form). Satu tempat supaya ke-18 stasiun tidak
 * masing-masing menampilkan enum mentah ("draft_ongoing"), nama bulan
 * Inggris ("03 Oct 2026") dan format angka campur ("17,432.50" vs
 * "25432.75").
 *
 * Hanya untuk TAMPILAN. Nilai di API, ekspor, dan atribut model tidak
 * berubah — kelas status CSS (mis. `wb-badge--saved`) tetap memakai nilai
 * enum aslinya.
 */
class Display
{
    /** Label Indonesia per nilai `status` record stasiun. */
    public const STATUS_LABELS = [
        'draft_ongoing' => 'Draft',
        'draft_paused' => 'Dijeda',
        'saved' => 'Tersimpan',
        'synced' => 'Tersinkron',
    ];

    public static function status(mixed $status): string
    {
        $value = $status instanceof RecordStatus ? $status->value : (string) ($status ?? '');

        if ($value === '') {
            return '-';
        }

        return self::STATUS_LABELS[$value] ?? $value;
    }

    /**
     * Tanggal dengan nama bulan Indonesia (translatedFormat, locale id),
     * di zona aplikasi. Default "04 Okt 2026".
     */
    public static function date(mixed $value, string $format = 'd M Y'): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        try {
            $date = $value instanceof \DateTimeInterface ? Carbon::instance($value) : Carbon::parse((string) $value);
        } catch (\Throwable) {
            return (string) $value;
        }

        return $date->setTimezone(AppTime::zone())->locale('id')->translatedFormat($format);
    }

    /** Tanggal + jam, "04 Okt 2026 20:35" (WIB). */
    public static function dateTime(mixed $value, string $format = 'd M Y H:i'): string
    {
        return self::date($value, $format);
    }

    /**
     * Angka format id-ID: titik ribuan, koma desimal. $decimals null =
     * pakai jumlah desimal yang memang ada pada nilainya (maks. 4), supaya
     * pH 7,25 tidak menjadi 7 dan 1500 tidak menjadi 1.500,00.
     */
    public static function number(mixed $value, ?int $decimals = null): string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return $value === null || $value === '' ? '-' : (string) $value;
        }

        $number = (float) $value;

        if ($decimals === null) {
            $decimals = 0;
            $text = rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');
            if (str_contains($text, '.')) {
                $decimals = strlen(substr($text, strpos($text, '.') + 1));
            }
        }

        return number_format($number, $decimals, ',', '.');
    }

    /**
     * Sel tabel/field generik: angka (int/float) diformat id-ID, boolean
     * menjadi Ya/Tidak, kosong menjadi "-", teks lain apa adanya. String
     * numerik SENGAJA tidak diformat — kode seperti "007" harus tetap utuh.
     */
    public static function value(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        if (is_int($value) || is_float($value)) {
            return self::number($value);
        }

        return (string) $value;
    }
}
