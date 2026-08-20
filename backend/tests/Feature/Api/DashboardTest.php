<?php

/**
 * DashboardTest (Feature/Api) — screen-025--dashboard-web /
 * usecase-025--dashboard-web.
 *
 * Integration tests for GET /api/dashboard/summary
 * (App\Http\Controllers\Api\DashboardController::summary()), one per
 * test_scenarios' api_test step(s). Exercises the real route -> 'auth:web,
 * sanctum' + 'role' middleware -> controller -> DashboardService -> Eloquent
 * chain against the sqlite in-memory testing DB, mirroring
 * DataBrowserWeighbridgeTest.php's setup/conventions.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
use App\Models\GradingRecord;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

// Scenario: "Lihat Dashboard Web — berhasil"
it('berhasil: returns aggregated KPI for today when no filter is given', function () {
    $today = Carbon::today()->toDateString();
    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt($today.' 08:00:00')->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/dashboard/summary');

    $response->assertOk();
    $response->assertJsonStructure([
        'weighbridge' => ['count', 'total_net_weight'],
        'grading' => ['count', 'total_netto', 'total_quantity'],
        'cages_track' => ['count', 'total_cages_tipped'],
    ]);
    $response->assertJsonPath('weighbridge.count', 1);
});

it('Mill Management and Admin can also access the summary endpoint', function () {
    $this->actingAs($this->millManagement, 'web')->getJson('/api/dashboard/summary')->assertOk();
    $this->actingAs($this->admin, 'web')->getJson('/api/dashboard/summary')->assertOk();
});

it('returns 403 for the Operator role', function () {
    $this->actingAs($this->operator, 'web')->getJson('/api/dashboard/summary')->assertStatus(403);
});

// Scenario: "Lihat Dashboard Web — Filter Diterapkan"
it('filters by date range and business_unit_id when provided', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherBusinessUnit)->create();

    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt('2026-02-05 08:00:00')->create();
    WeighbridgeRecord::factory()->forStation($otherStation)->arrivedAt('2026-02-05 08:00:00')->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/dashboard/summary?'.http_build_query([
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-10',
        'business_unit_id' => $this->businessUnit->id,
    ]));

    $response->assertOk();
    $response->assertJsonPath('weighbridge.count', 1);
});

// Scenario: "Lihat Dashboard Web — Tidak Ada Data Sesuai Filter"
it('returns zero counts when no records match the filter, not an error', function () {
    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/dashboard/summary?'.http_build_query([
        'date_from' => '2020-01-01',
        'date_to' => '2020-01-02',
    ]));

    $response->assertOk();
    $response->assertJsonPath('weighbridge.count', 0);
    $response->assertJsonPath('grading.count', 0);
    $response->assertJsonPath('cages_track.count', 0);
});

// Scenario: "Lihat Dashboard Web — Filter Tanggal Tidak Valid"
it('returns 422 INVALID_DATE_RANGE when date_from > date_to', function () {
    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/dashboard/summary?'.http_build_query([
        'date_from' => '2026-02-10',
        'date_to' => '2026-02-01',
    ]));

    $response->assertStatus(422);
});

it('aggregates grading and cages_track figures correctly in one call', function () {
    $today = Carbon::today()->toDateString();

    GradingRecord::factory()->forStation($this->station)->onDate($today)
        ->create(['netto' => 4000, 'quantity' => 100]);

    $cagesTrack = CagesTrackRecord::factory()->forStation($this->station)->onDate($today)->create();
    CagesTippedTime::factory()->forRecord($cagesTrack)->create(['total_cages' => 5]);

    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/dashboard/summary');

    $response->assertOk();
    $response->assertJsonPath('grading.total_netto', 4000);
    $response->assertJsonPath('cages_track.total_cages_tipped', 5);
});
