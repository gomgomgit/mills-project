<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Corporate;
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
