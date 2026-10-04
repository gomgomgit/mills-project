<?php

/**
 * Fixture for mobile/tests/e2e/sync-and-verification.spec.ts (audit
 * 2026-10-04). Run INSIDE the backend app via:
 *
 *   php artisan tinker --execute="require '<abs path to this file>';"
 *
 * Builds an ISOLATED test mill so the spec never touches the demo mill
 * (operator01's "Business Unit A") or the web suite's "BU Browser Test":
 *
 *   - Business Unit "BU Mobile Sync E2E" with Mills Setting
 *     immediate_sync_enabled = TRUE (write-through ON for this mill only)
 *   - two Production Lines, each with an active station of all 18 types
 *   - a Reporting Period covering today with every station type OPEN,
 *     EXCEPT `pressing`, which stays DRAFT — so a Pressing save is rejected
 *     by the server (422 PERIOD_CLOSED) on purpose
 *   - accounts mse2e-operator01 / mse2e-supervisor01 (Passw0rd!)
 *
 * Idempotent (firstOrCreate / updateOrCreate on natural keys). Prints one
 * JSON line with the ids the spec needs. Never deletes anything.
 */

use App\Enums\StationType;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\MillSetting;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$bu = BusinessUnit::where('name', 'BU Mobile Sync E2E')->first()
    ?? BusinessUnit::factory()->create(['name' => 'BU Mobile Sync E2E']);

MillSetting::updateOrCreate(
    ['business_unit_id' => $bu->id],
    ['app_name' => 'Mobile Sync E2E', 'immediate_sync_enabled' => true],
);

$lines = [];
foreach (['PL Sync E2E 1', 'PL Sync E2E 2'] as $lineName) {
    $line = ProductionLine::firstOrCreate(
        ['business_unit_id' => $bu->id, 'name' => $lineName],
        ProductionLine::factory()->make(['business_unit_id' => $bu->id, 'name' => $lineName])->toArray(),
    );
    foreach (StationType::cases() as $type) {
        if ($type === StationType::Other) {
            continue;
        }
        Station::firstOrCreate(
            ['production_line_id' => $line->id, 'type' => $type],
            [
                'business_unit_id' => $bu->id,
                'name' => ucwords(str_replace('-', ' ', $type->value)),
                'type' => $type,
                'is_active' => true,
            ],
        );
    }
    $lines[] = ['id' => $line->id, 'name' => $line->name];
}

$users = [];
foreach (['mse2e-operator01' => UserRole::Operator, 'mse2e-supervisor01' => UserRole::Supervisor] as $username => $role) {
    $user = User::updateOrCreate(
        ['username' => $username],
        [
            'name' => ucfirst(str_replace('-', ' ', $username)),
            'password_hash' => Hash::make('Passw0rd!'),
            'role' => $role,
            'business_unit_id' => $bu->id,
            'is_active' => true,
        ],
    );
    $users[$username] = $user->id;
}

$today = now('Asia/Jakarta')->toDateString();
$period = Period::where('business_unit_id', $bu->id)
    ->whereDate('start_date', '<=', $today)
    ->whereDate('end_date', '>=', $today)
    ->first();

if ($period === null) {
    $period = Period::create([
        'business_unit_id' => $bu->id,
        'name' => 'Periode Sync E2E '.$today,
        'start_date' => now('Asia/Jakarta')->subDays(3)->toDateString(),
        'end_date' => now('Asia/Jakarta')->addDays(3)->toDateString(),
        'created_by' => $users['mse2e-supervisor01'],
    ]);
}

foreach (StationType::cases() as $type) {
    if ($type === StationType::Other) {
        continue;
    }
    PeriodStation::updateOrCreate(
        ['period_id' => $period->id, 'station_type' => $type->value],
        ['status' => $type->value === 'pressing' ? 'draft' : 'open'],
    );
}

echo 'FIXTURE_JSON '.json_encode([
    'business_unit_id' => $bu->id,
    'lines' => $lines,
    'users' => $users,
    'period_id' => $period->id,
    'grading_parameters' => \App\Models\GradingParameter::orderBy('sort_order')->get(['id', 'name', 'uom', 'sort_order']),
]).PHP_EOL;
