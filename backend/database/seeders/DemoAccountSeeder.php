<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Corporate;
use App\Models\ProductionLine;
use App\Models\User;
use App\Services\ProductionLineService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the full demo corporate/company/business-unit/production-line
 * hierarchy plus every role's user accounts, for a comprehensive local dev
 * dataset (2026-08-20 expansion — "hapus semua data dan seed ulang" +
 * "seeder harus memasukkan semua base dan dummy data").
 *
 * LOAD-BEARING: `admin` / `supervisor01` / `operator01` (all password
 * `Passw0rd!`, business unit "Business Unit A" / code `BU-A`) are
 * hard-relied-on by `mobile/tests/e2e/helpers.ts`'s login() and several
 * Playwright specs — DO NOT rename/remove these three accounts or change
 * BU-A's `code`. Everything else in this seeder is free to reshape.
 *
 * Stations are no longer created directly here (that bypassed
 * BusinessUnitService's old auto-provisioning, which itself moved to
 * ProductionLineService::create() on 2026-08-20 — see that service's own
 * DEFAULT_STATIONS). Every Business Unit below gets 1-2 real Production
 * Lines via the real service call, so seeded stations behave identically
 * to ones created through the UI (same 15-canonical-station provisioning,
 * same production_line_id/business_unit_id wiring).
 */
class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $productionLineService = app(ProductionLineService::class);

        // --- Corporate 1: PT Sawit Nusantara (2 companies, 2 business units) ---
        $corporateA = Corporate::firstOrCreate(['name' => 'PT Sawit Nusantara'], [
            'corporate_code' => 'CORP-A',
        ]);

        $companyA1 = Company::firstOrCreate([
            'corporate_id' => $corporateA->id,
            'name' => 'PT Sawit Nusantara Mill 1',
        ], ['company_code' => 'COMP-A1']);

        $businessUnitA = BusinessUnit::firstOrCreate(
            ['code' => 'BU-A'],
            ['company_id' => $companyA1->id, 'name' => 'Business Unit A'],
        );

        $companyA2 = Company::firstOrCreate([
            'corporate_id' => $corporateA->id,
            'name' => 'PT Sawit Nusantara Mill 2',
        ], ['company_code' => 'COMP-A2']);

        $businessUnitB = BusinessUnit::firstOrCreate(
            ['code' => 'BU-B'],
            ['company_id' => $companyA2->id, 'name' => 'Business Unit B'],
        );

        // --- Corporate 2: PT Kelapa Sawit Makmur (1 company, 2 business units) ---
        $corporateB = Corporate::firstOrCreate(['name' => 'PT Kelapa Sawit Makmur'], [
            'corporate_code' => 'CORP-B',
        ]);

        $companyB1 = Company::firstOrCreate([
            'corporate_id' => $corporateB->id,
            'name' => 'PT Kelapa Sawit Makmur Mill 1',
        ], ['company_code' => 'COMP-B1']);

        $businessUnitC = BusinessUnit::firstOrCreate(
            ['code' => 'BU-C'],
            ['company_id' => $companyB1->id, 'name' => 'Business Unit C'],
        );

        $businessUnitD = BusinessUnit::firstOrCreate(
            ['code' => 'BU-D'],
            ['company_id' => $companyB1->id, 'name' => 'Business Unit D'],
        );

        $businessUnits = [$businessUnitA, $businessUnitB, $businessUnitC, $businessUnitD];

        // --- Production Lines (real service call — auto-provisions 15 stations each) ---
        // BU-A gets 2 lines (so mobile/web Production Line picker flows have
        // something real to pick between); the other 3 get 1 line each.
        $productionLinesByBusinessUnit = [];

        foreach ($businessUnits as $index => $businessUnit) {
            $lineCount = $businessUnit->id === $businessUnitA->id ? 2 : 1;
            $lines = [];

            for ($i = 1; $i <= $lineCount; $i++) {
                $existing = ProductionLine::where('business_unit_id', $businessUnit->id)
                    ->where('name', "Line {$i}")
                    ->first();

                if ($existing !== null) {
                    $lines[] = $existing;

                    continue;
                }

                $result = $productionLineService->create([
                    'business_unit_id' => $businessUnit->id,
                    'name' => "Line {$i}",
                ]);

                $lines[] = ProductionLine::findOrFail($result['id']);
            }

            $productionLinesByBusinessUnit[$businessUnit->id] = $lines;
        }

        // --- Users ---
        // Global admins (no business_unit_id).
        $admins = [
            ['username' => 'admin', 'name' => 'Admin'],
            ['username' => 'admin2', 'name' => 'Admin Dua'],
        ];

        foreach ($admins as $account) {
            User::firstOrCreate(
                ['username' => $account['username']],
                [
                    'password_hash' => Hash::make('Passw0rd!'),
                    'name' => $account['name'],
                    'role' => 'admin',
                    'business_unit_id' => null,
                    'is_active' => true,
                ],
            );
        }

        // Per-business-unit users: 1 supervisor, 1 mill_management, 2-3 operators.
        // BU-A keeps its exact original load-bearing usernames
        // (supervisor01/operator01) as the FIRST supervisor/operator created.
        $businessUnitSlugs = [
            $businessUnitA->id => 'a',
            $businessUnitB->id => 'b',
            $businessUnitC->id => 'c',
            $businessUnitD->id => 'd',
        ];

        $operatorSeq = 1;
        $supervisorSeq = 1;

        foreach ($businessUnits as $businessUnit) {
            $slug = $businessUnitSlugs[$businessUnit->id];

            $supervisorUsername = $businessUnit->id === $businessUnitA->id ? 'supervisor01' : "supervisor-{$slug}";
            User::firstOrCreate(
                ['username' => $supervisorUsername],
                [
                    'password_hash' => Hash::make('Passw0rd!'),
                    'name' => 'Supervisor '.strtoupper($slug),
                    'role' => 'supervisor',
                    'business_unit_id' => $businessUnit->id,
                    'is_active' => true,
                ],
            );
            $supervisorSeq++;

            User::firstOrCreate(
                ['username' => "millmanagement-{$slug}"],
                [
                    'password_hash' => Hash::make('Passw0rd!'),
                    'name' => 'Mill Management '.strtoupper($slug),
                    'role' => 'mill_management',
                    'business_unit_id' => $businessUnit->id,
                    'is_active' => true,
                ],
            );

            $operatorCount = $businessUnit->id === $businessUnitA->id ? 3 : 2;

            for ($i = 1; $i <= $operatorCount; $i++) {
                $operatorUsername = ($businessUnit->id === $businessUnitA->id && $i === 1)
                    ? 'operator01'
                    : "operator-{$slug}{$i}";

                User::firstOrCreate(
                    ['username' => $operatorUsername],
                    [
                        'password_hash' => Hash::make('Passw0rd!'),
                        'name' => "Operator {$slug}{$i}",
                        'role' => 'operator',
                        'business_unit_id' => $businessUnit->id,
                        'is_active' => true,
                    ],
                );
            }
        }

        // One inactive user per business unit, for "akun nonaktif" login-error flows.
        foreach ($businessUnits as $businessUnit) {
            $slug = $businessUnitSlugs[$businessUnit->id];

            User::firstOrCreate(
                ['username' => "inactive-{$slug}"],
                [
                    'password_hash' => Hash::make('Passw0rd!'),
                    'name' => 'Inactive User '.strtoupper($slug),
                    'role' => 'operator',
                    'business_unit_id' => $businessUnit->id,
                    'is_active' => false,
                ],
            );
        }
    }
}
