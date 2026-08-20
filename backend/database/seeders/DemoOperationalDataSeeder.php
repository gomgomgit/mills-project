<?php

namespace Database\Seeders;

use App\Enums\RecordStatus;
use App\Models\BusinessUnit;
use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
use App\Models\GradingDetail;
use App\Models\GradingParameter;
use App\Models\GradingRecord;
use App\Models\MillSetting;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use Illuminate\Database\Seeder;

/**
 * Seeds Weighbridge / Grading / Cages Track operational records (+ their
 * child rows) spread across the last 14 days, plus explicit Mill Setting
 * branding for a couple of Business Units — 2026-08-20 comprehensive demo
 * data expansion. Runs after DemoAccountSeeder (needs real stations/users)
 * and DemoMachineryDataSeeder is independent but ordered after it for
 * consistency.
 *
 * Per Business Unit, per active Weighbridge/Grading/Cages Track station:
 * ~4-5 Weighbridge records, ~3-4 Grading records (each referencing a real
 * Weighbridge record + 2-4 Grading Detail child rows against the 16
 * canonical Grading Parameters), ~3-4 Cages Track records (each with 2-5
 * Cages Tipped Time child rows). Mix of statuses/types so mobile draft-
 * status displays and web Dashboard/Laporan Manajemen date-range views
 * both have real, varied data to render.
 */
class DemoOperationalDataSeeder extends Seeder
{
    public function run(): void
    {
        $gradingParameterIds = GradingParameter::pluck('id')->all();

        BusinessUnit::all()->each(function (BusinessUnit $businessUnit) use ($gradingParameterIds): void {
            $operator = User::where('business_unit_id', $businessUnit->id)->where('role', 'operator')->first();
            $supervisor = User::where('business_unit_id', $businessUnit->id)->where('role', 'supervisor')->first();
            $millManagement = User::where('business_unit_id', $businessUnit->id)->where('role', 'mill_management')->first();

            if ($operator === null) {
                return;
            }

            $weighbridgeStations = Station::where('business_unit_id', $businessUnit->id)
                ->where('type', 'weighbridge')->where('is_active', true)->get();
            $gradingStations = Station::where('business_unit_id', $businessUnit->id)
                ->where('type', 'grading')->where('is_active', true)->get();
            $cagesTrackStations = Station::where('business_unit_id', $businessUnit->id)
                ->where('type', 'cages-track')->where('is_active', true)->get();

            $weighbridgeRecords = collect();

            $weighbridgeStations->each(function (Station $station) use ($operator, $supervisor, $millManagement, &$weighbridgeRecords): void {
                for ($i = 0; $i < fake()->numberBetween(4, 5); $i++) {
                    $daysAgo = fake()->numberBetween(0, 13);
                    $type = fake()->randomElement(['receive', 'dispatch']);
                    $status = fake()->randomElement([
                        RecordStatus::Synced, RecordStatus::Synced, RecordStatus::Saved,
                        RecordStatus::DraftOngoing, RecordStatus::DraftPaused,
                    ]);

                    $record = WeighbridgeRecord::factory()
                        ->forStation($station)
                        ->ofType($type)
                        ->arrivedAt(now()->subDays($daysAgo)->setTime(fake()->numberBetween(6, 17), fake()->numberBetween(0, 59)))
                        ->status($status)
                        ->create([
                            'created_by' => $operator->id,
                            'checked_by' => fake()->boolean(60) && $supervisor !== null ? $supervisor->id : null,
                            'acknowledged_by' => fake()->boolean(40) && $millManagement !== null ? $millManagement->id : null,
                        ]);

                    $weighbridgeRecords->push($record);
                }
            });

            $gradingStations->each(function (Station $station) use ($operator, $supervisor, $millManagement, $weighbridgeRecords, $gradingParameterIds): void {
                for ($i = 0; $i < fake()->numberBetween(3, 4); $i++) {
                    $daysAgo = fake()->numberBetween(0, 13);
                    $linkedWeighbridge = $weighbridgeRecords->isNotEmpty() ? $weighbridgeRecords->random() : null;

                    $record = GradingRecord::factory()
                        ->forStation($station)
                        ->onDate(now()->subDays($daysAgo))
                        ->status(RecordStatus::Synced)
                        ->create(array_filter([
                            'created_by' => $operator->id,
                            'checked_by' => fake()->boolean(60) && $supervisor !== null ? $supervisor->id : null,
                            'acknowledged_by' => fake()->boolean(40) && $millManagement !== null ? $millManagement->id : null,
                            'weighbridge_record_id' => $linkedWeighbridge?->id,
                            'license_plate_no' => $linkedWeighbridge?->vehicle_number,
                            'estate_supplier' => $linkedWeighbridge?->estate_supplier,
                            'division' => $linkedWeighbridge?->division,
                        ], fn ($v) => $v !== null));

                    $detailCount = fake()->numberBetween(2, 4);
                    $usedParameterIds = fake()->randomElements($gradingParameterIds, min($detailCount, count($gradingParameterIds)));

                    foreach ($usedParameterIds as $parameterId) {
                        GradingDetail::factory()
                            ->forGradingRecord($record)
                            ->forGradingParameter($parameterId)
                            ->create();
                    }
                }
            });

            $cagesTrackStations->each(function (Station $station) use ($operator, $supervisor, $millManagement): void {
                for ($i = 0; $i < fake()->numberBetween(3, 4); $i++) {
                    $daysAgo = fake()->numberBetween(0, 13);
                    $cagesTipped = fake()->numberBetween(8, 15);

                    $record = CagesTrackRecord::factory()
                        ->forStation($station)
                        ->onDate(now()->subDays($daysAgo))
                        ->status(RecordStatus::Synced)
                        ->create([
                            'created_by' => $operator->id,
                            'checked_by' => fake()->boolean(60) && $supervisor !== null ? $supervisor->id : null,
                            'acknowledged_by' => fake()->boolean(40) && $millManagement !== null ? $millManagement->id : null,
                            'cages_tipped' => $cagesTipped,
                        ]);

                    $rowCount = fake()->numberBetween(2, 5);
                    $usedHours = fake()->randomElements(range(6, 18), $rowCount);
                    sort($usedHours);

                    // CagesTippedTimeFactory's own `definition()` unconditionally
                    // calls faker->unique()->numberBetween(0, 23) for
                    // `tipped_hour` (only 24 possible values) BEFORE our
                    // override below is merged on top — Laravel factories
                    // always evaluate definition() first regardless of the
                    // override, and that call's unique-tracking pool is not
                    // reachable/resettable from here (a separate Faker
                    // instance from the global fake() helper). Every field is
                    // supplied explicitly below anyway, so bypass the factory
                    // and create the model directly — avoids the narrow pool
                    // entirely rather than widening the factory
                    // (test-infrastructure change, out of scope here).
                    foreach ($usedHours as $hour) {
                        $checkedCages = fake()->randomElements(range(1, $cagesTipped), fake()->numberBetween(1, min(5, $cagesTipped)));
                        sort($checkedCages);

                        CagesTippedTime::create([
                            'cages_track_record_id' => $record->id,
                            'tipped_hour' => $hour,
                            'checked_cage_numbers' => implode(',', $checkedCages),
                            'total_cages' => count($checkedCages),
                            'cages_remain' => max(0, $cagesTipped - count($checkedCages)),
                        ]);
                    }
                }
            });
        });

        // Explicit Mill Setting branding for the first 2 Business Units — the
        // rest get their MillSetting row lazily via getOrCreate() on first
        // access (app_name defaults to the business unit's own name then).
        BusinessUnit::limit(2)->get()->each(function (BusinessUnit $businessUnit): void {
            MillSetting::firstOrCreate(
                ['business_unit_id' => $businessUnit->id],
                ['app_name' => $businessUnit->name.' — Mill Smart Log'],
            );
        });
    }
}
