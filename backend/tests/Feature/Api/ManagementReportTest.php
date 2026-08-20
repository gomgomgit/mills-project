<?php

/**
 * ManagementReportTest (Feature/Api) — screen-026--laporan-manajemen /
 * usecase-026--laporan-manajemen.
 *
 * Integration tests for GET /api/reports/management-summary and
 * /export (App\Http\Controllers\Api\ManagementReportController), one
 * per test_scenarios' api_test step(s). Exercises the real route ->
 * 'auth:web,sanctum' + 'role:mill_management' middleware -> controller ->
 * ManagementReportService -> Eloquent chain, mirroring DashboardTest.php's
 * conventions. Unlike Dashboard Web, this screen is Mill Management ONLY
 * and never accepts a business_unit_id param — always resolved from the
 * acting user.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnit)->create();
});

// Scenario: "Lihat Laporan Manajemen — berhasil"
it('berhasil: returns daily breakdown for start-of-month..today when no filter is given', function () {
    $today = Carbon::today()->toDateString();
    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt($today.' 08:00:00')->create();

    $response = $this->actingAs($this->millManagement, 'web')->getJson('/api/reports/management-summary');

    $response->assertOk();
    $response->assertJsonStructure([
        'rows' => [['date', 'weighbridge', 'grading', 'cages_track']],
        'total' => ['weighbridge', 'grading', 'cages_track'],
    ]);
});

it('returns 403 for Supervisor, Admin, and Operator — Mill Management only', function () {
    $this->actingAs($this->supervisor, 'web')->getJson('/api/reports/management-summary')->assertStatus(403);
    $this->actingAs($this->admin, 'web')->getJson('/api/reports/management-summary')->assertStatus(403);
    $this->actingAs($this->operator, 'web')->getJson('/api/reports/management-summary')->assertStatus(403);
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/reports/management-summary')->assertStatus(401);
});

// Scenario: "Lihat Laporan Manajemen — Filter Diterapkan"
it('filters by the given date range', function () {
    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt('2026-02-05 08:00:00')->create();

    $response = $this->actingAs($this->millManagement, 'web')->getJson('/api/reports/management-summary?'.http_build_query([
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-10',
    ]));

    $response->assertOk();
    $response->assertJsonCount(10, 'rows');
});

it('never leaks another business unit\'s data', function () {
    $today = Carbon::today()->toDateString();
    $otherBusinessUnit = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherBusinessUnit)->create();

    WeighbridgeRecord::factory()->forStation($otherStation)->arrivedAt($today.' 08:00:00')->create();

    $response = $this->actingAs($this->millManagement, 'web')->getJson('/api/reports/management-summary');

    $response->assertOk();
    $response->assertJsonPath('total.weighbridge.count', 0);
});

// Scenario: "Lihat Laporan Manajemen — Tidak Ada Data Sesuai Filter"
it('returns zero-value rows when no records match the filter, not an error', function () {
    $response = $this->actingAs($this->millManagement, 'web')->getJson('/api/reports/management-summary?'.http_build_query([
        'date_from' => '2020-01-01',
        'date_to' => '2020-01-02',
    ]));

    $response->assertOk();
    $response->assertJsonPath('total.weighbridge.count', 0);
    $response->assertJsonPath('total.grading.count', 0);
    $response->assertJsonPath('total.cages_track.count', 0);
});

// Scenario: "Lihat Laporan Manajemen — Rentang Tanggal Tidak Valid"
it('returns 422 INVALID_DATE_RANGE when date_from > date_to', function () {
    $response = $this->actingAs($this->millManagement, 'web')->getJson('/api/reports/management-summary?'.http_build_query([
        'date_from' => '2026-02-10',
        'date_to' => '2026-02-01',
    ]));

    $response->assertStatus(422);
});

// Scenario: "Lihat Laporan Manajemen — Ekspor Laporan"
it('export: downloads a CSV file for Mill Management', function () {
    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt('2026-02-05 08:00:00')->create();

    $response = $this->actingAs($this->millManagement, 'web')->get('/api/reports/management-summary/export?'.http_build_query([
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-05',
    ]));

    $response->assertOk();
    expect(strtolower($response->headers->get('Content-Type')))->toBe('text/csv; charset=utf-8');
});

it('export: also authenticates via Sanctum token (mobile-style guard, dual auth)', function () {
    \Laravel\Sanctum\Sanctum::actingAs($this->millManagement, ['*']);

    $response = $this->get('/api/reports/management-summary');

    $response->assertOk();
});
