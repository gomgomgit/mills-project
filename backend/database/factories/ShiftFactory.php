<?php

namespace Database\Factories;

use App\Models\ProductionLine;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 *
 * screen-170--kelola-shift test infrastructure.
 *
 * Default-nya shift "Pagi" 07:00–15:00 pada Production Line baru — yaitu
 * shift yang dimulai TEPAT di awal hari produksi, sehingga sebuah line yang
 * hanya berisi satu shift default punya cakupan yang mudah diprediksi
 * (gap 16 jam, starts_at_production_day true).
 *
 * JAM DISIMPAN SEBAGAI 'H:i:s'. Kolomnya TIME: PostgreSQL memulangkannya
 * 'HH:MM:SS' sementara SQLite memulangkan apa pun yang tersimpan, jadi
 * factory menulis bentuk panjangnya supaya test tidak pernah lolos di SQLite
 * dengan nilai yang tidak akan pernah muncul di produksi.
 * ShiftService::minuteOfDay() membaca kedua bentuk, jadi state di bawah
 * boleh diberi 'HH:MM' maupun 'HH:MM:SS'.
 *
 * TIDAK ADA STATE HARI/TANGGAL BERLAKU, dan itu disengaja — jadwal shift
 * berlaku sama setiap hari (keputusan pemilik produk 2026-10-15).
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        return [
            'production_line_id' => ProductionLine::factory(),
            'name' => 'Pagi',
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function forProductionLine(ProductionLine|string $productionLine): self
    {
        return $this->state(fn () => [
            'production_line_id' => $productionLine instanceof ProductionLine
                ? $productionLine->id
                : $productionLine,
        ]);
    }

    public function named(string $name): self
    {
        return $this->state(fn () => ['name' => $name]);
    }

    /**
     * Rentang jam apa pun, termasuk yang MELEWATI TENGAH MALAM
     * (->range('23:00', '07:00')) — bentuk paling umum untuk shift malam dan
     * kasus jebakan yang wajib ada di setiap test tumpang tindih.
     */
    public function range(string $startTime, string $endTime): self
    {
        return $this->state(fn () => [
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }

    /** Pagi 07:00–15:00 — blok pertama hari produksi. */
    public function pagi(): self
    {
        return $this->named('Pagi')->range('07:00:00', '15:00:00');
    }

    /** Siang 15:00–23:00 — bersambung TEPAT dari Pagi (irisan nol menit). */
    public function siang(): self
    {
        return $this->named('Siang')->range('15:00:00', '23:00:00');
    }

    /**
     * Malam 23:00–07:00 — MELEWATI TENGAH MALAM. Satu blok utuh berdurasi
     * 480 menit dengan start_offset_minutes 960, bukan dua potongan.
     */
    public function malam(): self
    {
        return $this->named('Malam')->range('23:00:00', '07:00:00');
    }
}
