<?php

namespace App\Support;

/**
 * ChartAxis — sumbu tegak "angka bulat" untuk grafik garis laporan (md-lc*).
 *
 * KENAPA ADA (temuan audit 2026-10-04 #6). Grafik tren membagi rentang
 * mentah (mis. 0 .. maks×1,12) menjadi 4 bagian sama, sehingga labelnya
 * angka ganjil seperti 295.294 / 590.588, dan pada grafik suhu label yang
 * dibulatkan ke 0 desimal menjadi tidak berjarak sama (90, 92, 93, 95,
 * 96). Di sini rentang dibulatkan ke kelipatan langkah 1/2/2,5/5 × 10^n,
 * sehingga setiap label bulat, berjarak sama, dan jumlah desimalnya
 * mengikuti langkahnya.
 */
class ChartAxis
{
    /**
     * @return array{lo: float, hi: float, step: float, ticks: list<float>, decimals: int}
     */
    public static function nice(float $lo, float $hi, int $targetIntervals = 4): array
    {
        if ($hi < $lo) {
            [$lo, $hi] = [$hi, $lo];
        }

        if ($hi - $lo < 1e-9) {
            $pad = abs($hi) > 0 ? abs($hi) * 0.1 : 1.0;
            $lo -= $pad;
            $hi += $pad;
        }

        $step = self::clean(self::niceStep(($hi - $lo) / max(1, $targetIntervals)));
        $niceLo = floor($lo / $step + 1e-9) * $step;
        $niceHi = ceil($hi / $step - 1e-9) * $step;

        $ticks = [];
        $count = (int) round(($niceHi - $niceLo) / $step);
        for ($i = 0; $i <= $count; $i++) {
            $ticks[] = self::clean($niceLo + $i * $step);
        }

        return [
            'lo' => self::clean($niceLo),
            'hi' => self::clean($niceHi),
            'step' => (float) $step,
            'ticks' => $ticks,
            'decimals' => self::decimalsFor($step),
        ];
    }

    /** Buang galat pecahan biner (500000.0000000001 → 500000.0). */
    private static function clean(float $value): float
    {
        $clean = (float) sprintf('%.12g', $value);

        return $clean == 0.0 ? 0.0 : $clean;
    }

    private static function niceStep(float $raw): float
    {
        $exponent = floor(log10($raw));
        $base = 10 ** $exponent;
        $fraction = $raw / $base;

        foreach ([1, 2, 2.5, 5, 10] as $candidate) {
            if ($fraction <= $candidate + 1e-9) {
                return $candidate * $base;
            }
        }

        return 10 * $base;
    }

    private static function decimalsFor(float $step): int
    {
        for ($decimals = 0; $decimals < 6; $decimals++) {
            if (abs($step * 10 ** $decimals - round($step * 10 ** $decimals)) < 1e-6) {
                return $decimals;
            }
        }

        return 6;
    }
}
