<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Corporate;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the demo corporate/company/business-unit hierarchy and the 3 demo
 * user accounts (admin / supervisor01 / operator01, all password
 * `Passw0rd!`, same business unit) that `mobile/tests/e2e/helpers.ts`'s
 * login() helper depends on.
 *
 * This existed only as ad-hoc `php artisan tinker` commands run directly
 * against the dev DB in prior sessions — undocumented anywhere in code, so
 * every `php artisan migrate:fresh` (run at least twice so far, once for
 * the Grading entity-catalog round and once for the Cages Track round)
 * silently wiped it and broke every Playwright e2e spec's login() call
 * until someone noticed and re-ran the same tinker script by hand.
 * Idempotent via firstOrCreate() keyed on unique fields, so re-running
 * (e.g. via `php artisan db:seed --class=DemoAccountSeeder` after a fresh
 * migrate) never duplicates rows.
 */
class DemoAccountSeeder extends Seeder
{
    /**
     * Same fixed canonical 15-station list as
     * `BusinessUnitService::DEFAULT_STATIONS` (protected there, so not
     * reusable from a seeder) and mobile's `DEFAULT_STATIONS`
     * (localSchema.ts) — kept in sync manually, same as those two already
     * are with each other. This seeder pre-dates that auto-provisioning
     * rule (`BusinessUnit::firstOrCreate()` here bypasses the service
     * entirely), so 'Business Unit A' was left with zero stations —
     * confirmed as the actual cause of a "no active Weighbridge station"
     * 422 hit via the mobile sync feature (2026-08-20). `code` left null
     * for every row, same reasoning as the service (nullable+unique,
     * never collides).
     */
    private const DEFAULT_STATIONS = [
        ['name' => 'Weighbridge', 'type' => 'weighbridge', 'is_active' => true],
        ['name' => 'Grading', 'type' => 'grading', 'is_active' => true],
        ['name' => 'Cages Track', 'type' => 'cages-track', 'is_active' => true],
        ['name' => 'Sterilizer', 'type' => 'other', 'is_active' => false],
        ['name' => 'Thresher', 'type' => 'other', 'is_active' => false],
        ['name' => 'Press', 'type' => 'other', 'is_active' => false],
        ['name' => 'Clarification', 'type' => 'other', 'is_active' => false],
        ['name' => 'Kernel Plant', 'type' => 'other', 'is_active' => false],
        ['name' => 'Boiler', 'type' => 'other', 'is_active' => false],
        ['name' => 'Effluent Treatment', 'type' => 'other', 'is_active' => false],
        ['name' => 'Loading Ramp', 'type' => 'other', 'is_active' => false],
        ['name' => 'Digester', 'type' => 'other', 'is_active' => false],
        ['name' => 'Engine Room', 'type' => 'other', 'is_active' => false],
        ['name' => 'Water Treatment', 'type' => 'other', 'is_active' => false],
        ['name' => 'Bulking Storage', 'type' => 'other', 'is_active' => false],
    ];

    public function run(): void
    {
        $corporate = Corporate::firstOrCreate(['name' => 'PT Sawit Nusantara']);

        $company = Company::firstOrCreate([
            'corporate_id' => $corporate->id,
            'name' => 'PT Sawit Nusantara Mill 1',
        ]);

        $businessUnit = BusinessUnit::firstOrCreate(
            ['code' => 'BU-A'],
            ['company_id' => $company->id, 'name' => 'Business Unit A'],
        );

        foreach (self::DEFAULT_STATIONS as $station) {
            Station::firstOrCreate(
                ['business_unit_id' => $businessUnit->id, 'name' => $station['name']],
                ['type' => $station['type'], 'is_active' => $station['is_active']],
            );
        }

        $accounts = [
            ['username' => 'admin', 'name' => 'Admin', 'role' => 'admin'],
            ['username' => 'supervisor01', 'name' => 'Supervisor Satu', 'role' => 'supervisor'],
            ['username' => 'operator01', 'name' => 'Operator Satu', 'role' => 'operator'],
        ];

        foreach ($accounts as $account) {
            User::firstOrCreate(
                ['username' => $account['username']],
                [
                    'password_hash' => Hash::make('Passw0rd!'),
                    'name' => $account['name'],
                    'role' => $account['role'],
                    'business_unit_id' => $businessUnit->id,
                    'is_active' => true,
                ],
            );
        }
    }
}
