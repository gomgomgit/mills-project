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

    /**
     * `type = threshing` — added additively for
     * screen-057--form-threshing-web's test suite (ThreshingRecordServiceTest
     * / FormThreshingTest), mirroring `weighbridge()`/`grading()`/
     * `cagesTrack()` above exactly.
     */
    public function threshing(): self
    {
        return $this->state(fn () => ['type' => StationType::Threshing]);
    }

    /**
     * `type = pressing` — added additively for
     * screen-058--form-pressing-web's test suite (PressingRecordServiceTest
     * / FormPressingTest), mirroring
     * `weighbridge()`/`grading()`/`cagesTrack()`/`threshing()` above
     * exactly.
     */
    public function pressing(): self
    {
        return $this->state(fn () => ['type' => StationType::Pressing]);
    }

    /**
     * `type = depricarping` — added additively for
     * screen-059--form-depricarping-web's test suite
     * (DepricarpingRecordServiceTest / FormDepricarpingTest), mirroring
     * `weighbridge()`/`grading()`/`cagesTrack()`/`threshing()`/`pressing()`
     * above exactly.
     */
    public function depricarping(): self
    {
        return $this->state(fn () => ['type' => StationType::Depricarping]);
    }

    /**
     * `type = kernel-plant` — added additively for
     * screen-060--form-kernel-plant-web's test suite
     * (KernelPlantRecordServiceTest / FormKernelPlantTest), mirroring
     * `weighbridge()`/`grading()`/`cagesTrack()`/`threshing()`/`pressing()`/
     * `depricarping()` above exactly.
     */
    public function kernelPlant(): self
    {
        return $this->state(fn () => ['type' => StationType::KernelPlant]);
    }

    /**
     * `type = solid-waste-disposal` — added additively for
     * screen-111--form-solid-waste-disposal-web's test suite
     * (SolidWasteDisposalRecordServiceTest / FormSolidWasteDisposalTest),
     * mirroring `weighbridge()`/`grading()`/`cagesTrack()`/etc. above
     * exactly.
     */
    public function solidWasteDisposal(): self
    {
        return $this->state(fn () => ['type' => StationType::SolidWasteDisposal]);
    }

    /**
     * `type = process-water` — added additively for
     * screen-112--form-process-water-web's test suite
     * (ProcessWaterRecordServiceTest / FormProcessWaterTest), mirroring
     * `weighbridge()`/`grading()`/`cagesTrack()`/`threshing()`/etc. above
     * exactly.
     */
    public function processWater(): self
    {
        return $this->state(fn () => ['type' => StationType::ProcessWater]);
    }

    /**
     * `type = kernel-dispatch` — added additively for
     * screen-113--form-kernel-dispatch-web's test suite
     * (KernelDispatchRecordServiceTest / FormKernelDispatchTest), mirroring
     * `weighbridge()`/`grading()`/`cagesTrack()`/`solidWasteDisposal()`/
     * `processWater()` above exactly.
     */
    public function kernelDispatch(): self
    {
        return $this->state(fn () => ['type' => StationType::KernelDispatch]);
    }

    /**
     * `type = cpo-dispatch` — added additively for
     * screen-114--form-cpo-dispatch-web's test suite
     * (CpoDispatchRecordServiceTest / FormCpoDispatchTest), mirroring
     * `weighbridge()`/`grading()`/`cagesTrack()`/`solidWasteDisposal()`/
     * `processWater()`/`kernelDispatch()` above exactly.
     */
    public function cpoDispatch(): self
    {
        return $this->state(fn () => ['type' => StationType::CpoDispatch]);
    }

    /**
     * `type = effluent-plant` — added additively for
     * screen-115--form-effluent-plant-web's test suite
     * (EffluentPlantRecordServiceTest / FormEffluentPlantTest), mirroring
     * `weighbridge()`/`grading()`/`cagesTrack()`/`processWater()`/etc. above
     * exactly.
     */
    public function effluentPlant(): self
    {
        return $this->state(fn () => ['type' => StationType::EffluentPlant]);
    }

    /**
     * `type = storage-tank` — added additively for
     * screen-116--form-storage-tank-web's test suite
     * (StorageTankRecordServiceTest / FormStorageTankTest), mirroring
     * `weighbridge()`/`grading()`/`cagesTrack()`/`processWater()`/
     * `effluentPlant()`/etc. above exactly.
     */
    public function storageTank(): self
    {
        return $this->state(fn () => ['type' => StationType::StorageTank]);
    }

    /**
     * `type = engine-room` — added additively for
     * screen-117--form-engine-room-web's test suite
     * (EngineRoomRecordServiceTest / FormEngineRoomTest), mirroring
     * `weighbridge()`/`grading()`/`cagesTrack()`/`processWater()`/
     * `effluentPlant()`/`storageTank()`/etc. above exactly.
     */
    public function engineRoom(): self
    {
        return $this->state(fn () => ['type' => StationType::EngineRoom]);
    }

    /**
     * `type = boiler-room` — added additively for
     * screen-118--form-boiler-room-web's test suite
     * (BoilerRoomRecordServiceTest / FormBoilerRoomTest), mirroring
     * `weighbridge()`/`grading()`/`cagesTrack()`/`processWater()`/
     * `effluentPlant()`/`storageTank()`/`engineRoom()`/etc. above exactly.
     */
    public function boilerRoom(): self
    {
        return $this->state(fn () => ['type' => StationType::BoilerRoom]);
    }

    /**
     * `type = clarification` — added additively for
     * screen-119--form-clarification-web's test suite
     * (ClarificationRecordServiceTest / FormClarificationTest), mirroring
     * `weighbridge()`/`grading()`/`cagesTrack()`/`processWater()`/
     * `effluentPlant()`/`storageTank()`/`engineRoom()`/`boilerRoom()`/etc.
     * above exactly.
     */
    public function clarification(): self
    {
        return $this->state(fn () => ['type' => StationType::Clarification]);
    }

    /**
     * `type = process-quality-control` — added additively for
     * screen-120--form-process-quality-control-web's test suite
     * (ProcessQualityControlRecordServiceTest / FormProcessQualityControlTest),
     * mirroring `weighbridge()`/`grading()`/`cagesTrack()`/`processWater()`/
     * `effluentPlant()`/`storageTank()`/`engineRoom()`/`boilerRoom()`/
     * `clarification()`/etc. above exactly. This is the FINAL of the 10
     * MVP stations promoted 2026-08-31.
     */
    public function processQualityControl(): self
    {
        return $this->state(fn () => ['type' => StationType::ProcessQualityControl]);
    }

    /**
     * `type = sterilizer` — added additively for
     * screen-126--form-sterilizer-web's test suite
     * (SterilizerRecordServiceTest / FormSterilizerTest), mirroring
     * `weighbridge()`/`grading()`/`cagesTrack()`/`processQualityControl()`/
     * etc. above exactly. This is the FINAL station type promoted out of
     * `other` for this project — 0 placeholders remain after this.
     */
    public function sterilizer(): self
    {
        return $this->state(fn () => ['type' => StationType::Sterilizer]);
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
