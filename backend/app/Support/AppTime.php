<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * AppTime — satu tempat untuk aturan jam aplikasi (WIB, config('app.timezone')).
 *
 * KENAPA ADA. Kolom datetime di database NAIF (tanpa zona) dan Eloquent TIDAK
 * mengonversi zona saat atribut di-assign: cast `datetime` mem-parse
 * "2026-10-03T13:35:00Z" lalu memformatnya 'Y-m-d H:i:s' apa adanya, sehingga
 * yang tersimpan adalah "2026-10-03 13:35:00" — jam UTC yang kemudian dibaca
 * sebagai jam WIB. Mobile Weighbridge mengirim `new Date().toISOString()`
 * (berakhiran Z), jadi transaksi 20:35 WIB tampil "13:35" di web.
 *
 * ATURANNYA. String ber-penanda zona (Z atau ±hh:mm / ±hhmm di akhir) adalah
 * sebuah INSTAN, dan dikonversi ke jam WIB sebelum disimpan. String TANPA
 * penanda zona ("2026-10-03T20:35" dari datetime-local web, atau
 * "2026-10-03T20:35:00" dari form mobile lain) sudah berupa jam dinding WIB
 * dan dibiarkan apa adanya. Nilai yang tidak bisa di-parse juga dibiarkan,
 * supaya rule `date` di validator yang menolaknya dengan pesan yang benar.
 */
class AppTime
{
    /** Penanda zona di akhir string waktu: "Z", "+07:00", "+0700", "-03". */
    private const ZONE_SUFFIX = '/\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?\s*(?:Z|[+-]\d{2}(?::?\d{2})?)$/i';

    public static function zone(): string
    {
        return (string) config('app.timezone');
    }

    /**
     * Normalkan input datetime dari klien ke jam dinding aplikasi
     * ('Y-m-d H:i:s'). Lihat docblock kelas.
     */
    public static function normalizeClientDateTime(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || ! preg_match(self::ZONE_SUFFIX, $trimmed)) {
            return $value;
        }

        try {
            return Carbon::parse($trimmed)->setTimezone(self::zone())->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * Batas atas tanggal kejadian yang masuk akal: hari ini (zona aplikasi)
     * + config('app.event_date_max_days_ahead') hari, inklusif — default 1,
     * yaitu BESOK. Satu hari kelonggaran untuk jam perangkat yang sedikit
     * maju dan shift malam yang melewati tengah malam; tanggal di luar itu
     * (mis. tahun 7278) ditolak. null = batas dimatikan lewat konfigurasi
     * (nilai kosong atau negatif).
     */
    public static function latestEventDate(): ?Carbon
    {
        $days = config('app.event_date_max_days_ahead', 1);

        if ($days === null || $days === '' || ! is_numeric($days) || (int) $days < 0) {
            return null;
        }

        return Carbon::today(self::zone())->addDays((int) $days);
    }
}
