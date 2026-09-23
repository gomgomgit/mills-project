<?php

namespace Database\Factories;

use App\Enums\PeriodStatus;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Period>
 *
 * screen-128--kelola-periode-pelaporan test infrastructure.
 *
 * Defaults to a DRAFT, all-station-types (station_type = null) period
 * covering one calendar month — the least constrained shape, so a test
 * that only cares about one attribute does not have to spell out the
 * other five. Use forBusinessUnit()/stationType()/range() to pin the
 * scope, and open()/closed() to move the status.
 *
 * NOTE ON station_type: it is a FK to `station_types`.`code`, so any
 * value passed to stationType() must exist in that master table (it is
 * seeded by 2026_09_22_000029_create_station_types_table with all 18
 * canonical types plus 'other'). Pass the code string, e.g.
 * 'sterilizer', not an App\Enums\StationType case.
 */
class PeriodFactory extends Factory
{
    protected $model = Period::class;

    public function definition(): array
    {
        $start = Carbon::create(2026, 10, 1);

        return [
            'business_unit_id' => BusinessUnit::factory(),
            'station_type' => null,
            'name' => 'Periode '.$this->faker->unique()->numerify('####'),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->endOfMonth()->toDateString(),
            'status' => PeriodStatus::Draft->value,
            'closed_by' => null,
            'closed_at' => null,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function forBusinessUnit(BusinessUnit|string $businessUnit): self
    {
        return $this->state(fn () => [
            'business_unit_id' => $businessUnit instanceof BusinessUnit ? $businessUnit->id : $businessUnit,
        ]);
    }

    /**
     * Scope the period to ONE station type. Pass null (or use the default)
     * for the "covers every station type in this mill" scope.
     */
    public function stationType(?string $stationType): self
    {
        return $this->state(fn () => ['station_type' => $stationType]);
    }

    public function named(string $name): self
    {
        return $this->state(fn () => ['name' => $name]);
    }

    /**
     * Both bounds are inclusive, exactly as the overlap check and the
     * unverified-count query treat them.
     */
    public function range(string $startDate, string $endDate): self
    {
        return $this->state(fn () => [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
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
     * A closed period — closed_by/closed_at are always set together,
     * because the reopen() path clears both together and the list row
     * renders both columns.
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
