<?php

namespace Database\Factories;

use App\Enums\PeriodStatus;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PeriodStation>
 *
 * screen-128--kelola-periode-pelaporan test infrastructure.
 *
 * Satu jenis stasiun di dalam satu periode, beserta status tutup/bukanya.
 * Dipakai langsung ketika test butuh mengatur baris per stasiun secara
 * terpisah — terutama periode yang SEPARUH tertutup (Sterilizer closed,
 * Clarification masih open), bentuk yang menjadi alasan tabel ini dipisah.
 *
 * Induk defaultnya dibuat dengan Period::factory()->noStations(): tanpa itu
 * induknya sudah punya satu baris untuk setiap jenis stasiun aktif dan baris
 * ini akan menabrak UNIQUE(period_id, station_type).
 *
 * station_type adalah FK ke `station_types`.`code` dan TIDAK BOLEH NULL —
 * konsep "berlaku semua jenis stasiun" lewat NULL sudah tidak ada.
 */
class PeriodStationFactory extends Factory
{
    protected $model = PeriodStation::class;

    public function definition(): array
    {
        return [
            'period_id' => Period::factory()->noStations(),
            'station_type' => 'sterilizer',
            'status' => PeriodStatus::Draft->value,
            'closed_by' => null,
            'closed_at' => null,
        ];
    }

    public function forPeriod(Period|string $period): self
    {
        return $this->state(fn () => [
            'period_id' => $period instanceof Period ? $period->id : $period,
        ]);
    }

    public function stationType(string $stationType): self
    {
        return $this->state(fn () => ['station_type' => $stationType]);
    }

    public function draft(): self
    {
        return $this->state(fn () => [
            'status' => PeriodStatus::Draft->value,
            'closed_by' => null,
            'closed_at' => null,
        ]);
    }

    public function open(): self
    {
        return $this->state(fn () => [
            'status' => PeriodStatus::Open->value,
            'closed_by' => null,
            'closed_at' => null,
        ]);
    }

    /**
     * closed_by dan closed_at selalu disetel bersama — jalur reopen()
     * mengosongkan keduanya bersama.
     */
    public function closed(User|string|null $closedBy = null, ?string $closedAt = null): self
    {
        return $this->state(fn () => [
            'status' => PeriodStatus::Closed->value,
            'closed_by' => $closedBy instanceof User
                ? $closedBy->id
                : ($closedBy ?? User::factory()),
            'closed_at' => $closedAt ?? Carbon::create(2026, 11, 1, 9, 14, 0)->toDateTimeString(),
        ]);
    }
}
