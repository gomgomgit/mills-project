<?php

namespace Database\Factories;

use App\Enums\StationType;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Station;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Station>
 *
 * Test infrastructure — created by test-writer-agent for
 * screen-016--data-browser-weighbridge-web (no prior screen needed a
 * Station factory; WeighbridgeRecord::station() is a required belongsTo,
 * so WeighbridgeRecordFactory needs this to exist).
 *
 * `other()`/`withCode()` states below were added additively by
 * test-writer-agent for screen-030--kelola-station's test suite
 * (StationServiceTest / KelolaStationTest, both Api and Livewire) — the
 * existing `definition()` default and `weighbridge()`/`forBusinessUnit()`
 * states above are completely unchanged.
 *
 * 2026-08-20 (entity-catalog v9): `production_line_id` is now a required
 * (NOT NULL) column — `definition()` auto-creates a ProductionLine and
 * derives `business_unit_id` from it, so every one of this factory's many
 * pre-existing call sites across the suite keeps working unchanged.
 * `forBusinessUnit()` is updated the same way (auto-creates a matching
 * ProductionLine under the forced business unit, so the two FKs never
 * disagree); new `forProductionLine()` state added for tests that need to
 * pin the production line directly.
 */
class StationFactory extends Factory
{
    protected $model = Station::class;

    public function definition(): array
    {
        return [
            'production_line_id' => ProductionLine::factory(),
            'business_unit_id' => function (array $attributes) {
                return ProductionLine::find($attributes['production_line_id'])?->business_unit_id;
            },
            'name' => 'Weighbridge '.$this->faker->unique()->numerify('##'),
            'type' => StationType::Weighbridge,
            'is_active' => true,
        ];
    }

    public function weighbridge(): self
    {
        return $this->state(fn () => ['type' => StationType::Weighbridge]);
    }

    /**
     * `type = grading` — added additively for screen-023--form-grading-web's
     * test suite (GradingRecordServiceTest / FormGradingTest), mirroring
     * `weighbridge()` above exactly.
     */
    public function grading(): self
    {
        return $this->state(fn () => ['type' => StationType::Grading]);
    }

    /**
     * `type = cages-track` — added additively for
     * screen-024--form-cages-track-web's test suite
     * (CagesTrackRecordServiceTest / FormCagesTrackTest), mirroring
     * `weighbridge()`/`grading()` above exactly.
     */
    public function cagesTrack(): self
    {
        return $this->state(fn () => ['type' => StationType::CagesTrack]);
    }

    public function forBusinessUnit(BusinessUnit|string $businessUnit): self
    {
        return $this->state(function () use ($businessUnit) {
            $businessUnitId = $businessUnit instanceof BusinessUnit ? $businessUnit->id : $businessUnit;

            return [
                'business_unit_id' => $businessUnitId,
                'production_line_id' => ProductionLine::factory()->forBusinessUnit($businessUnitId),
            ];
        });
    }

    /**
     * Pins both FKs from an existing ProductionLine — used by tests that
     * need a Station to belong to a specific, already-created production
     * line rather than getting a freshly factory-created one.
     */
    public function forProductionLine(ProductionLine|string $productionLine): self
    {
        return $this->state(function () use ($productionLine) {
            $productionLine = $productionLine instanceof ProductionLine
                ? $productionLine
                : ProductionLine::findOrFail($productionLine);

            return [
                'production_line_id' => $productionLine->id,
                'business_unit_id' => $productionLine->business_unit_id,
            ];
        });
    }

    /**
     * `type = other` + `is_active = false` — the only valid combination
     * for type=other per StationService::validate()'s cross-field rule
     * (is_active may never be true when type is "other"). Used by
     * screen-030--kelola-station's test suite to seed a valid "Other"
     * station without tripping that rule.
     */
    public function other(): self
    {
        return $this->state(fn () => [
            'type' => StationType::Other,
            'is_active' => false,
        ]);
    }

    /**
     * Sets an explicit `code` — used by screen-030--kelola-station's test
     * suite for uniqueness-conflict fixtures.
     */
    public function withCode(string $code): self
    {
        return $this->state(fn () => ['code' => $code]);
    }

    /**
     * Sets an explicit `icon` override — used by screen-034--mills-setting's
     * test suite. Added additively; `definition()`'s default (icon
     * omitted, so it stays null) is unchanged.
     */
    public function withIcon(string $icon): self
    {
        return $this->state(fn () => ['icon' => $icon]);
    }
}
