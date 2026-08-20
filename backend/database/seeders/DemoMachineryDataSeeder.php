<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use App\Models\MachineryGroup;
use App\Models\Station;
use Illuminate\Database\Seeder;

/**
 * Seeds Machinery Group / Machinery (+ Insurance / Tax-Purchase child rows)
 * across a spread of stations per Business Unit — 2026-08-20 comprehensive
 * demo-data expansion ("seeder harus memasukkan semua base dan dummy data
 * ke semua table yang diperlukan untuk setiap flow"). Runs after
 * DemoAccountSeeder (needs real Station rows, which only exist once
 * Production Lines have been provisioned).
 *
 * Per Business Unit: picks the 3 active stations (Weighbridge/Grading/
 * Cages Track) across ALL of that unit's Production Lines, plus 2 of the
 * 12 `other`-typed placeholder stations (for realistic non-empty
 * placeholder-station detail views) — 2-3 Machinery Groups each, 2-4
 * Machinery per group, each Machinery gets 1 Insurance row (mix of past/
 * future expiry) and 1 Tax/Purchase row.
 */
class DemoMachineryDataSeeder extends Seeder
{
    public function run(): void
    {
        // Plain incrementing counter for Machinery `name` — MachineryFactory's
        // own default (faker->unique()->numerify('##'), only 100 possible
        // values) overflows at this seed's scale even when overridden, since
        // Laravel factories always evaluate definition() first regardless of
        // the override merged on top. A counter sidesteps Faker's
        // unique-tracking entirely rather than widening the factory
        // (test-infrastructure change, out of scope here).
        $machineryCounter = 0;

        BusinessUnit::all()->each(function (BusinessUnit $businessUnit) use (&$machineryCounter): void {
            $activeStations = Station::where('business_unit_id', $businessUnit->id)
                ->where('is_active', true)
                ->get();

            $placeholderStations = Station::where('business_unit_id', $businessUnit->id)
                ->where('is_active', false)
                ->inRandomOrder()
                ->limit(2)
                ->get();

            $stations = $activeStations->merge($placeholderStations);

            $stations->each(function (Station $station) use ($businessUnit, &$machineryCounter): void {
                $groupCount = fake()->numberBetween(2, 3);

                for ($g = 0; $g < $groupCount; $g++) {
                    $group = MachineryGroup::factory()
                        ->forStation($station)
                        ->create(['business_unit_id' => $businessUnit->id]);

                    $machineryCount = fake()->numberBetween(2, 4);

                    for ($m = 0; $m < $machineryCount; $m++) {
                        $machineryCounter++;

                        // MachineryFactory::definition() unconditionally calls
                        // faker->unique()->numerify('##') for `name` (only 100
                        // possible values) as part of building its base
                        // attributes array — that call runs (and can exhaust
                        // its pool) BEFORE any ->create([...]) override is
                        // merged on top, regardless of what we override. Every
                        // field is supplied explicitly below anyway, so bypass
                        // the factory and create the model directly (same
                        // pattern as CagesTippedTime in
                        // DemoOperationalDataSeeder) — manually replicating
                        // what forFullMachineryGroup() derives.
                        $machinery = \App\Models\Machinery::create([
                            'machinery_group_id' => $group->id,
                            'station_id' => $group->station_id,
                            'business_unit_id' => $group->business_unit_id,
                            'name' => 'Machine '.str_pad((string) $machineryCounter, 4, '0', STR_PAD_LEFT),
                            'equipment_code' => 'EQ-'.str_pad((string) $machineryCounter, 8, '0', STR_PAD_LEFT),
                        ]);

                        \App\Models\MachineryInsurance::factory()
                            ->forMachinery($machinery)
                            ->create([
                                'insurance_expiry_date' => fake()->boolean(50)
                                    ? fake()->dateTimeBetween('-1 year', '-1 day')->format('Y-m-d')
                                    : fake()->dateTimeBetween('+1 day', '+2 years')->format('Y-m-d'),
                            ]);

                        \App\Models\MachineryTaxPurchase::factory()
                            ->forMachinery($machinery)
                            ->create();
                    }
                }
            });
        });
    }
}
